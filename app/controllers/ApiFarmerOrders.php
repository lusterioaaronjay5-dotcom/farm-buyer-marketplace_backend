<?php

class ApiFarmerOrders extends Controller
{
    // Susunod na status na puwedeng i-set ng farmer
    private const NEXT_STATUS = [
        'confirmed'        => 'preparing',
        'preparing'        => 'out_for_delivery',
        'out_for_delivery' => 'completed',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
        $this->call->helper('farmer');
        $this->call->model('Product', 'product');
        $this->call->model('Order', 'order');
        $this->call->model('Delivery', 'delivery');
    }

    /**
     * Kinukuha ang lat/lng ng address gamit ang OpenStreetMap Nominatim.
     * Ibinabalik ang [null, null] kung hindi mahanap.
     */
    private function geocodeAddress($address)
    {
        $address = trim((string) $address);
        if ($address === '') {
            return [null, null];
        }

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q'            => $address . ', Philippines',
            'format'       => 'json',
            'limit'        => 1,
            'countrycodes' => 'ph',
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 8,
            CURLOPT_USERAGENT      => 'FarmConnect/1.0 (capstone project)',
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $status !== 200) {
            return [null, null];
        }

        $data = json_decode($response, true);
        if (empty($data[0]['lat']) || empty($data[0]['lon'])) {
            return [null, null];
        }

        return [
            round((float) $data[0]['lat'], 7),
            round((float) $data[0]['lon'], 7),
        ];
    }

    /**
     * Address ng farmer na naka-geocode, para sa panimulang punto ng rider.
     */
    private function farmerOrigin($farmerId)
    {
        $rows = $this->db->table('users')->where('user_id', $farmerId)->get_all() ?: [];
        $me = !empty($rows) ? (array) $rows[0] : [];

        return $this->geocodeAddress($me['address'] ?? '');
    }

    /**
     * Lahat ng produkto ng farmer, keyed by product_id. (1 query)
     */
    private function farmerProducts($farmerId)
    {
        $rows = $this->product->_find_all_by('farmer_id', $farmerId) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $p = (array) $row;
            $map[(int) $p['product_id']] = $p;
        }

        return $map;
    }

    /**
     * Binubuo ang isang order mula sa mga naka-prefetch nang data. Walang query dito.
     * Gumagamit ng unit_price na itinala noong nag-checkout, hindi ng kasalukuyang presyo.
     */
    private function formatOrder(array $order, array $items, array $products, array $buyers, array $payments, array $deliveries)
    {
        $orderId  = (int) $order['order_id'];
        $buyer    = $buyers[(int) $order['buyer_id']] ?? [];
        $payment  = $payments[$orderId] ?? null;
        $delivery = $deliveries[$orderId] ?? null;

        $lines    = [];
        $subtotal = 0;

        foreach ($items as $item) {
            $product   = $products[(int) $item['product_id']] ?? [];
            $qty       = (int) ($item['quantity'] ?? 0);
            $price     = (float) ($item['unit_price'] ?? $product['price'] ?? 0);
            $lineTotal = $price * $qty;
            $subtotal += $lineTotal;

            $lines[] = [
                'product_id'   => (int) $item['product_id'],
                'product_name' => $product['product_name'] ?? 'Produkto',
                'unit'         => $product['unit'] ?? '',
                'quantity'     => $qty,
                'price'        => $price,
                'line_total'   => round($lineTotal, 2),
            ];
        }

        return [
            'order_id'    => $orderId,
            'status'      => $order['status'],
            'created_at'  => $order['order_date'] ?? null,
            'buyer'       => [
                'full_name' => $buyer['full_name'] ?? null,
                'phone'     => $buyer['phone'] ?? null,
            ],
            'items'       => $lines,
            'subtotal'    => round($subtotal, 2),
            'payment'     => $payment ? [
                'status'    => $payment['payment_status'],
                'method'    => $payment['payment_method'] ?? null,
                'reference' => $payment['transaction_reference'] ?? null,
                'paid_at'   => $payment['paid_at'] ?? null,
            ] : null,
            'delivery'    => $delivery ? [
                'status'           => $delivery['delivery_status'],
                'address'          => $delivery['delivery_address'] ?? null,
                'latitude'         => $delivery['latitude'] ?? null,
                'longitude'        => $delivery['longitude'] ?? null,
                'dispatched_at'    => isset($delivery['dispatched_at']) ? (int) $delivery['dispatched_at'] : null,
                'origin_latitude'  => $delivery['origin_latitude'] ?? null,
                'origin_longitude' => $delivery['origin_longitude'] ?? null,
            ] : null,
            'next_status' => self::NEXT_STATUS[$order['status']] ?? null,
        ];
    }

    /**
     * GET /api/farmer/orders
     * Limang query lang anuman ang dami ng order.
     */
    public function index()
    {
        $this->api->require_method('GET');
        $farmerId = farmer_require_farmer($this->api);

        // 1. Produkto ng farmer
        $products   = $this->farmerProducts($farmerId);
        $productIds = array_keys($products);

        // 2. Lahat ng items na may produkto ng farmer
        $itemsByOrder = api_group_by(
            api_fetch_in($this->db, 'order_items', 'product_id', $productIds),
            'order_id'
        );
        $orderIds = array_keys($itemsByOrder);

        // 3. Mga order
        $orders = api_key_by(
            api_fetch_in($this->db, 'orders', 'order_id', $orderIds),
            'order_id'
        );

        // 4. Buyers, payments, at deliveries ng mga order na iyon
        $buyerIds   = array_map(fn ($o) => $o['buyer_id'], $orders);
        $buyers     = api_key_by(api_fetch_in($this->db, 'users', 'user_id', $buyerIds), 'user_id');
        $payments   = api_key_by(api_fetch_in($this->db, 'payments', 'order_id', $orderIds), 'order_id');
        $deliveries = api_key_by(api_fetch_in($this->db, 'deliveries', 'order_id', $orderIds), 'order_id');

        $result = [];
        foreach ($itemsByOrder as $orderId => $items) {
            if (!isset($orders[$orderId])) {
                continue;
            }
            $result[] = $this->formatOrder(
                $orders[$orderId],
                $items,
                $products,
                $buyers,
                $payments,
                $deliveries
            );
        }

        usort($result, fn ($a, $b) => $b['order_id'] <=> $a['order_id']);

        $this->api->respond([
            'orders'   => $result,
        ]);
    }

    /**
     * POST /api/farmer/orders/status
     * Body: order_id, status (dapat ang susunod na status lang)
     * Kapag out_for_delivery, itatala ang oras ng pag-alis at lokasyon ng farmer.
     */
    public function updateStatus()
    {
        $this->api->require_method('POST');
        $farmerId = farmer_require_farmer($this->api);
        $body = $this->api->body();

        $orderId   = (int) ($body['order_id'] ?? 0);
        $newStatus = trim((string) ($body['status'] ?? ''));

        if ($orderId <= 0 || $newStatus === '') {
            $this->api->respond_error('order_id at status ay required.', 422);
        }

        // Sinisiguro na may produkto ng farmer ang order (isang query lang)
        $productIds = array_keys($this->farmerProducts($farmerId));
        $owned = empty($productIds)
            ? []
            : $this->db->table('order_items')
                ->where('order_id', $orderId)
                ->in('product_id', $productIds)
                ->get_all();

        if (empty($owned)) {
            $this->api->respond_error('Order not found.', 404);
        }

        $order = $this->order->_find($orderId);
        if (!$order) {
            $this->api->respond_error('Order not found.', 404);
        }
        $order = (array) $order;

        $expected = self::NEXT_STATUS[$order['status']] ?? null;
        if ($expected === null) {
            $this->api->respond_error('Walang susunod na status para sa order na ito.', 409);
        }

        if ($newStatus !== $expected) {
            $this->api->respond_error("Ang susunod na status ay '$expected'.", 422);
        }

        $this->order->_update($orderId, ['status' => $newStatus]);

        // Ang enum ng deliveries ay gumagamit ng "delivered" imbes na "completed"
        $deliveryStatus = $newStatus === 'completed' ? 'delivered' : $newStatus;

        $deliveryRows = $this->delivery->_find_all_by('order_id', $orderId) ?: [];
        if (!empty($deliveryRows)) {
            $delivery = (array) $deliveryRows[0];
            $update = ['delivery_status' => $deliveryStatus];

            // Kapag ipinadala na, itatala ang oras ng pag-alis at ang lokasyon ng farmer
            if ($newStatus === 'out_for_delivery') {
                [$originLat, $originLng] = $this->farmerOrigin($farmerId);
                $update['dispatched_at']    = time();
                $update['origin_latitude']  = $originLat;
                $update['origin_longitude'] = $originLng;
            }

            $this->delivery->_update((int) $delivery['delivery_id'], $update);
        }

        $this->api->respond([
            'message'  => 'Status updated.',
            'order_id' => $orderId,
            'status'   => $newStatus,
        ]);
    }
}