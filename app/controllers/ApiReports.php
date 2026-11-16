<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiReports extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
        $this->call->helper('farmer');
    }

    // Kinukuha ang from at to (YYYY-MM-DD) mula sa query string. Walang laman kung mali ang format.
    private function dateParams()
    {
        $from = trim((string) ($_GET['from'] ?? ''));
        $to   = trim((string) ($_GET['to'] ?? ''));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = '';
        }

        return [$from, $to];
    }

    // Nasa saklaw ba ng petsa ang order
    private function inRange($orderDate, $from, $to)
    {
        $d = substr((string) $orderDate, 0, 10);
        if ($from !== '' && $d < $from) {
            return false;
        }
        if ($to !== '' && $d > $to) {
            return false;
        }
        return true;
    }

    /**
     * GET /api/reports/admin?from=&to=
     * Bilang ng users at produkto, kabuuang sales, delivery fees, at talaan ng order.
     */
    public function admin()
    {
        $this->api->require_method('GET');
        api_require_role($this->api, 'admin', 'Admin lang ang puwedeng gumamit nito.');
        [$from, $to] = $this->dateParams();

        $usersById = [];
        $roleCounts = ['buyer' => 0, 'farmer' => 0, 'admin' => 0];
        foreach ($this->db->table('users')->get_all() ?: [] as $row) {
            $u = (array) $row;
            $usersById[(int) $u['user_id']] = $u;
            $roleCounts[$u['role']] = ($roleCounts[$u['role']] ?? 0) + 1;
        }

        $productRows = $this->db->table('products')->get_all() ?: [];
        $productsAvailable = 0;
        foreach ($productRows as $row) {
            if (((array) $row)['availability'] === 'available') {
                $productsAvailable++;
            }
        }

        $orders = [];
        $statusCounts = [];
        $salesCompleted = 0.0;
        $deliveryFees = 0.0;

        foreach ($this->db->table('orders')->get_all() ?: [] as $row) {
            $o = (array) $row;
            if (!$this->inRange($o['order_date'] ?? '', $from, $to)) {
                continue;
            }

            $status = $o['status'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            if ($status === 'completed') {
                $salesCompleted += (float) $o['total_amount'];
            }
            if ($status !== 'cancelled') {
                $deliveryFees += (float) ($o['delivery_fee'] ?? 0);
            }

            $buyer = $usersById[(int) $o['buyer_id']] ?? [];
            $orders[] = [
                'order_id'        => (int) $o['order_id'],
                'order_date'      => $o['order_date'] ?? null,
                'buyer_name'      => $buyer['full_name'] ?? '—',
                'status'          => $status,
                'delivery_method' => $o['delivery_method'] ?? 'delivery',
                'delivery_fee'    => (float) ($o['delivery_fee'] ?? 0),
                'total_amount'    => (float) $o['total_amount'],
            ];
        }

        usort($orders, fn ($a, $b) => $b['order_id'] <=> $a['order_id']);

        $this->api->respond([
            'from'    => $from,
            'to'      => $to,
            'summary' => [
                'users'              => $roleCounts,
                'products_total'     => count($productRows),
                'products_available' => $productsAvailable,
                'orders_total'       => count($orders),
                'orders_by_status'   => $statusCounts,
                'sales_completed'    => round($salesCompleted, 2),
                'delivery_fees'      => round($deliveryFees, 2),
            ],
            'orders'  => $orders,
        ]);
    }

    /**
     * GET /api/reports/farmer?from=&to=
     * Benta at kita kada produkto, average rating, at talaan ng order na may produkto ng farmer.
     */
    public function farmer()
    {
        $this->api->require_method('GET');
        $farmerId = farmer_require_farmer($this->api);
        [$from, $to] = $this->dateParams();

        $products = [];
        foreach ($this->db->table('products')->where('farmer_id', $farmerId)->get_all() ?: [] as $row) {
            $p = (array) $row;
            $products[(int) $p['product_id']] = $p;
        }
        $productIds = array_keys($products);

        $items = [];
        if (!empty($productIds)) {
            foreach (api_fetch_in($this->db, 'order_items', 'product_id', $productIds) as $row) {
                $items[] = (array) $row;
            }
        }

        $orderIds = array_values(array_unique(array_map(fn ($i) => (int) $i['order_id'], $items)));

        $ordersById = [];
        $buyers = [];
        if (!empty($orderIds)) {
            foreach (api_fetch_in($this->db, 'orders', 'order_id', $orderIds) as $row) {
                $o = (array) $row;
                $ordersById[(int) $o['order_id']] = $o;
            }
            $buyerIds = array_values(array_unique(array_map(fn ($o) => (int) $o['buyer_id'], $ordersById)));
            $buyers = api_key_by(api_fetch_in($this->db, 'users', 'user_id', $buyerIds), 'user_id');
        }

        // Ratings kada produkto
        $ratings = [];
        if (!empty($productIds)) {
            foreach (api_fetch_in($this->db, 'reviews', 'product_id', $productIds) as $row) {
                $r = (array) $row;
                $ratings[(int) $r['product_id']][] = (int) $r['rating'];
            }
        }

        $perProduct = [];      // benta at kita, hindi kasama ang cancelled
        $orderSubtotals = [];  // subtotal ng farmer kada order
        $orderInRange = [];

        foreach ($items as $item) {
            $order = $ordersById[(int) $item['order_id']] ?? null;
            if (!$order || !$this->inRange($order['order_date'] ?? '', $from, $to)) {
                continue;
            }

            $oid = (int) $order['order_id'];
            $pid = (int) $item['product_id'];
            $lineSub = (float) $item['subtotal'];

            $orderInRange[$oid] = $order;
            $orderSubtotals[$oid] = ($orderSubtotals[$oid] ?? 0) + $lineSub;

            if ($order['status'] !== 'cancelled') {
                $perProduct[$pid]['quantity'] = ($perProduct[$pid]['quantity'] ?? 0) + (int) $item['quantity'];
                $perProduct[$pid]['revenue']  = ($perProduct[$pid]['revenue'] ?? 0) + $lineSub;
            }
        }

        $productList = [];
        foreach ($products as $pid => $p) {
            $r = $ratings[$pid] ?? [];
            $productList[] = [
                'product_id'    => $pid,
                'product_name'  => $p['product_name'] ?? '',
                'quantity_sold' => (int) ($perProduct[$pid]['quantity'] ?? 0),
                'revenue'       => round((float) ($perProduct[$pid]['revenue'] ?? 0), 2),
                'rating_avg'    => $r ? round(array_sum($r) / count($r), 1) : null,
                'rating_count'  => count($r),
            ];
        }
        usort($productList, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);

        $orderList = [];
        $statusCounts = [];
        $salesCompleted = 0.0;

        foreach ($orderInRange as $oid => $o) {
            $buyer = (array) ($buyers[(int) $o['buyer_id']] ?? []);
            $status = $o['status'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            if ($status === 'completed') {
                $salesCompleted += $orderSubtotals[$oid];
            }

            $orderList[] = [
                'order_id'        => $oid,
                'order_date'      => $o['order_date'] ?? null,
                'buyer_name'      => $buyer['full_name'] ?? '—',
                'status'          => $status,
                'delivery_method' => $o['delivery_method'] ?? 'delivery',
                'subtotal'        => round($orderSubtotals[$oid], 2),
            ];
        }
        usort($orderList, fn ($a, $b) => $b['order_id'] <=> $a['order_id']);

        $this->api->respond([
            'from'    => $from,
            'to'      => $to,
            'summary' => [
                'products_total'   => count($products),
                'orders_total'     => count($orderList),
                'orders_by_status' => $statusCounts,
                'sales_completed'  => round($salesCompleted, 2),
            ],
            'products' => $productList,
            'orders'   => $orderList,
        ]);
    }

    /**
     * GET /api/reports/buyer?from=&to=
     * Kasaysayan ng sariling order ng buyer, gastos, at delivery fees.
     */
    public function buyer()
    {
        $this->api->require_method('GET');
        $buyerId = api_require_role($this->api, 'buyer', 'Buyer account lang ang puwedeng gumamit nito.');
        [$from, $to] = $this->dateParams();

        $orders = [];
        $statusCounts = [];
        $totalSpent = 0.0;
        $deliveryFees = 0.0;

        foreach ($this->db->table('orders')->where('buyer_id', $buyerId)->get_all() ?: [] as $row) {
            $o = (array) $row;
            if (!$this->inRange($o['order_date'] ?? '', $from, $to)) {
                continue;
            }

            $status = $o['status'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            if ($status !== 'cancelled') {
                $totalSpent  += (float) $o['total_amount'];
                $deliveryFees += (float) ($o['delivery_fee'] ?? 0);
            }

            $orders[] = [
                'order_id'        => (int) $o['order_id'],
                'order_date'      => $o['order_date'] ?? null,
                'status'          => $status,
                'delivery_method' => $o['delivery_method'] ?? 'delivery',
                'delivery_fee'    => (float) ($o['delivery_fee'] ?? 0),
                'total_amount'    => (float) $o['total_amount'],
            ];
        }

        usort($orders, fn ($a, $b) => $b['order_id'] <=> $a['order_id']);

        $this->api->respond([
            'from'    => $from,
            'to'      => $to,
            'summary' => [
                'orders_total'     => count($orders),
                'orders_by_status' => $statusCounts,
                'total_spent'      => round($totalSpent, 2),
                'delivery_fees'    => round($deliveryFees, 2),
            ],
            'orders'  => $orders,
        ]);
    }
}