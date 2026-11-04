<?php

class ApiFarmerDashboard extends Controller
{
    // Mga order na kailangan pang aksyunan ng farmer
    private const ACTIVE_STATUSES = ['confirmed', 'preparing', 'out_for_delivery'];
    private const LOW_STOCK_LIMIT = 10;

    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
        $this->call->helper('farmer');
        $this->call->model('Product', 'product');
        $this->call->model('Order', 'order');
        $this->call->model('OrderItem', 'order_item');
        $this->call->model('User', 'user');
    }

    /**
     * GET /api/farmer/dashboard
     * Limang query lang anuman ang dami ng data.
     */
    public function index()
    {
        $this->api->require_method('GET');
        $farmerId = farmer_require_farmer($this->api);

        // 1. Produkto ng farmer
        $products = [];
        foreach ($this->product->_find_all_by('farmer_id', $farmerId) ?: [] as $row) {
            $p = (array) $row;
            $products[(int) $p['product_id']] = $p;
        }

        $totalListings = count($products);
        $available = 0;
        $lowStock = [];

        foreach ($products as $p) {
            if (($p['availability'] ?? '') !== 'available') {
                continue;
            }
            $available++;

            $qty = (int) $p['quantity_available'];
            if ($qty <= self::LOW_STOCK_LIMIT) {
                $lowStock[] = [
                    'product_id'         => (int) $p['product_id'],
                    'product_name'       => $p['product_name'],
                    'quantity_available' => $qty,
                    'unit'               => $p['unit'],
                ];
            }
        }

        usort($lowStock, fn ($a, $b) => $a['quantity_available'] <=> $b['quantity_available']);
        $lowStock = array_slice($lowStock, 0, 5);

        // 2. Items ng lahat ng produkto ng farmer (1 query)
        $itemRows = farmer_fetch_in($this->db, 'order_items', 'product_id', array_keys($products));

        $orderTotals = []; // order_id => subtotal ng farmer
        foreach ($itemRows as $item) {
            $i = (array) $item;
            $productId = (int) $i['product_id'];
            $orderId   = (int) $i['order_id'];
            $qty       = (int) $i['quantity'];
            $price     = (float) ($i['unit_price'] ?? $products[$productId]['price'] ?? 0);

            $orderTotals[$orderId] = ($orderTotals[$orderId] ?? 0) + ($price * $qty);
        }

        // 3. Mga order na may produkto ng farmer (1 query)
        $orderRows = api_key_by(farmer_fetch_in($this->db, 'orders', 'order_id', array_keys($orderTotals)), 'order_id');

        // 4. Mga buyer ng mga order na iyon (1 query)
        $buyerIds = array_map(fn ($o) => $o['buyer_id'], $orderRows);
        $buyers   = api_key_by(farmer_fetch_in($this->db, 'users', 'user_id', $buyerIds), 'user_id');

        $pendingOrders   = 0;
        $completedOrders = 0;
        $totalSales      = 0.0;
        $orders          = [];

        foreach ($orderTotals as $orderId => $subtotal) {
            if (!isset($orderRows[$orderId])) {
                continue;
            }
            $order  = $orderRows[$orderId];
            $status = $order['status'];

            if (in_array($status, self::ACTIVE_STATUSES, true)) {
                $pendingOrders++;
            }

            if ($status === 'completed') {
                $completedOrders++;
                $totalSales += $subtotal;
            }

            $buyer = $buyers[(int) $order['buyer_id']] ?? [];

            $orders[] = [
                'order_id'   => $orderId,
                'status'     => $status,
                'buyer_name' => $buyer['full_name'] ?? null,
                'subtotal'   => round($subtotal, 2),
                'created_at' => $order['order_date'] ?? null,
            ];
        }

        usort($orders, fn ($a, $b) => $b['order_id'] <=> $a['order_id']);

        $this->api->respond([
            'stats' => [
                'total_listings'   => $totalListings,
                'available'        => $available,
                'pending_orders'   => $pendingOrders,
                'completed_orders' => $completedOrders,
                'total_sales'      => round($totalSales, 2),
            ],
            'recent_orders' => array_slice($orders, 0, 5),
            'low_stock'     => $lowStock,
        ]);
    }
}