<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiOrders extends Controller
{
    // Delivery fee: base kada farmer, plus bayad kada kilometro
    private const DELIVERY_BASE_FEE = 30.0;
    private const DELIVERY_PER_KM   = 10.0;

    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
        $this->call->model('Product', 'product');
        $this->call->model('Order', 'order');
        $this->call->model('OrderItem', 'order_item');
        $this->call->model('Payment', 'payment');
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
     * Distansya sa kilometro ng dalawang coordinates (haversine formula).
     */
    private function distanceKm($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Delivery fee: base bawat farmer, plus bayad kada km mula sa farmer papunta sa buyer.
     * Kung walang address ang farmer, base fee lang ang sisingilin.
     */
    private function calculateDeliveryFee(array $farmerIds, $buyerLat, $buyerLng)
    {
        $fee = 0.0;

        foreach ($farmerIds as $farmerId) {
            $fee += self::DELIVERY_BASE_FEE;

            $rows = $this->db->table('users')->where('user_id', $farmerId)->get_all() ?: [];
            $farmer = !empty($rows) ? (array) $rows[0] : [];
            [$farmerLat, $farmerLng] = $this->geocodeAddress($farmer['address'] ?? '');

            if ($farmerLat === null || $buyerLat === null) {
                continue;
            }

            $fee += self::DELIVERY_PER_KM
                * $this->distanceKm($farmerLat, $farmerLng, $buyerLat, $buyerLng);
        }

        return round($fee, 2);
    }

    /**
     * GET /api/geocode?q=address
     * Ginagamit ng Cart para mag-auto-pin habang nagta-type ang buyer.
     */
    public function geocode()
    {
        api_require_role($this->api, 'buyer', 'Buyer account lang ang puwedeng mag-order.');

        $q = trim((string) ($_GET['q'] ?? ''));
        if (strlen($q) < 4) {
            $this->api->respond(['found' => false]);
        }

        [$lat, $lng] = $this->geocodeAddress($q);

        $this->api->respond([
            'found'     => $lat !== null,
            'latitude'  => $lat,
            'longitude' => $lng,
        ]);
    }

    /**
     * GET /api/orders
     * Ibinabalik ang lahat ng order ng buyer, kasama ang items, payment, at delivery.
     * Apat na query lang anuman ang dami ng order. Walang geocoding dito (nasa POST lang).
     */
    public function index()
    {
        $buyerId = api_require_role($this->api, 'buyer', 'Buyer account lang ang puwedeng mag-order.');

        // 1. Mga order ng buyer
        $orders = array_map(fn ($o) => (array) $o, $this->order->_find_all_by('buyer_id', $buyerId) ?: []);
        $orderIds = array_map(fn ($o) => (int) $o['order_id'], $orders);

        // 2. Items kasama ang product_id at pangalan ng produkto, sa isang JOIN query
        $itemRows = [];
        if (!empty($orderIds)) {
            $itemRows = $this->db->table('order_items')
                ->select('order_items.order_item_id, order_items.order_id, order_items.product_id, order_items.quantity, order_items.unit_price, order_items.subtotal, products.product_name')
                ->join('products', 'products.product_id = order_items.product_id')
                ->in('order_items.order_id', $orderIds)
                ->get_all() ?: [];
        }
        $itemsByOrder = api_group_by($itemRows, 'order_id');

        // 3. Payments at deliveries
        $payments   = api_key_by(api_fetch_in($this->db, 'payments', 'order_id', $orderIds), 'order_id');
        $deliveries = api_key_by(api_fetch_in($this->db, 'deliveries', 'order_id', $orderIds), 'order_id');

        $result = [];
        foreach ($orders as $order) {
            $orderId = (int) $order['order_id'];

            $detailedItems = [];
            foreach ($itemsByOrder[$orderId] ?? [] as $item) {
                $detailedItems[] = [
                    'order_item_id' => (int) $item['order_item_id'],
                    'product_id'    => (int) $item['product_id'],
                    'product_name'  => $item['product_name'] ?? '',
                    'quantity'      => (int) $item['quantity'],
                    'unit_price'    => (float) $item['unit_price'],
                    'subtotal'      => (float) $item['subtotal'],
                ];
            }

            $order['items']        = $detailedItems;
            $order['delivery_fee'] = (float) ($order['delivery_fee'] ?? 0);
            $order['payment']      = $payments[$orderId] ?? null;
            $order['delivery']     = $deliveries[$orderId] ?? null;
            $result[] = $order;
        }

        usort($result, fn ($a, $b) => $b['order_id'] <=> $a['order_id']);

        $this->api->respond(['orders' => $result]);
    }

    /**
     * POST /api/orders
     * Body: items [{product_id, quantity}], delivery_address, notes,
     *       delivery_method (delivery o pickup), latitude/longitude (optional, galing sa pin ng mapa)
     * Binabawasan ang stock sa loob ng transaction, kaya hindi nagkakahalo ang stock kapag sabay ang order.
     */
    public function store()
    {
        $this->api->require_method('POST');
        $buyerId = api_require_role($this->api, 'buyer', 'Buyer account lang ang puwedeng mag-order.');
        $body = $this->api->body();

        $items   = $body['items'] ?? [];
        $address = trim($body['delivery_address'] ?? '');
        $notes   = trim($body['notes'] ?? '');
        $method  = $body['delivery_method'] ?? 'delivery';

        if (!in_array($method, ['delivery', 'pickup'], true)) {
            $this->api->respond_error('Mali ang paraan ng delivery.', 422);
        }

        $hasLat = isset($body['latitude']) && $body['latitude'] !== '' && $body['latitude'] !== null;
        $hasLng = isset($body['longitude']) && $body['longitude'] !== '' && $body['longitude'] !== null;

        if ($hasLat !== $hasLng) {
            $this->api->respond_error('Kailangan ang latitude at longitude nang sabay.', 422);
        }

        $pinLat = $hasLat ? (float) $body['latitude'] : null;
        $pinLng = $hasLng ? (float) $body['longitude'] : null;

        if ($pinLat !== null && (abs($pinLat) > 90 || abs($pinLng) > 180)) {
            $this->api->respond_error('Mali ang coordinates ng pin.', 422);
        }

        if (!is_array($items) || empty($items)) {
            $this->api->respond_error('Walang laman ang order.', 422);
        }
        if ($address === '') {
            $this->api->respond_error('Kailangan ang delivery address.', 422);
        }

        // Pagsamahin ang parehong produkto para tama ang stock check
        $quantities = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity  = (int) ($item['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                $this->api->respond_error('Mali ang product o quantity.', 422);
            }

            $quantities[$productId] = ($quantities[$productId] ?? 0) + $quantity;
        }

        // Pre-check: validation at presyo bago ang transaction
        $lines = [];
        $subtotal = 0.0;

        foreach ($quantities as $productId => $quantity) {
            $product = $this->product->_find($productId);
            if (!$product) {
                $this->api->respond_error('Hindi mahanap ang isang produkto.', 404);
            }
            $product = (array) $product;

            if ($product['availability'] !== 'available') {
                $this->api->respond_error($product['product_name'] . ' ay hindi available.', 409);
            }
            if ($quantity > (int) $product['quantity_available']) {
                $this->api->respond_error('Kulang ang stock ng ' . $product['product_name'] . '.', 409);
            }

            $unitPrice = (float) $product['price'];
            $lineTotal = round($unitPrice * $quantity, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'product_id'   => $productId,
                'farmer_id'    => (int) $product['farmer_id'],
                'product_name' => $product['product_name'],
                'quantity'     => $quantity,
                'unit_price'   => $unitPrice,
                'subtotal'     => $lineTotal,
            ];
        }

        $subtotal = round($subtotal, 2);

        // Geocode bago ang transaction para hindi naka-hold ang transaction habang naghihintay sa Nominatim
        if ($pinLat !== null) {
            $lat = round($pinLat, 7);
            $lng = round($pinLng, 7);
        } else {
            [$lat, $lng] = $this->geocodeAddress($address);
        }

        // Delivery fee: wala kapag pickup. Idinadagdag sa total.
        $deliveryFee = $method === 'delivery'
            ? $this->calculateDeliveryFee(array_values(array_unique(array_column($lines, 'farmer_id'))), $lat, $lng)
            : 0.0;

        $total = round($subtotal + $deliveryFee, 2);

        // Transaction: stock decrement + order + order_items + delivery, lahat o wala
        $this->db->transaction();
        try {
            foreach ($lines as $line) {
                $affected = $this->db->table('products')
                    ->where('product_id', $line['product_id'])
                    ->where('quantity_available', '>=', $line['quantity'])
                    ->decrement('quantity_available', $line['quantity']);

                if ($affected < 1) {
                    throw new RuntimeException('Kulang na ang stock ng ' . $line['product_name'] . '.');
                }

                // Kung naubos na ang stock, markahan bilang hindi available
                $this->db->table('products')
                    ->where('product_id', $line['product_id'])
                    ->where('quantity_available', 0)
                    ->update(['availability' => 'unavailable']);
            }

            $orderId = $this->db->table('orders')->insert([
                'buyer_id'         => $buyerId,
                'order_date'       => date('Y-m-d H:i:s'),
                'total_amount'     => $total,
                'status'           => 'pending',
                'delivery_address' => $address,
                'delivery_method'  => $method,
                'delivery_fee'     => $deliveryFee,
                'notes'            => $notes !== '' ? $notes : null,
            ]);

            foreach ($lines as $line) {
                $this->db->table('order_items')->insert([
                    'order_id'   => $orderId,
                    'product_id' => $line['product_id'],
                    'quantity'   => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'subtotal'   => $line['subtotal'],
                ]);
            }

            $this->db->table('deliveries')->insert([
                'order_id'         => $orderId,
                'delivery_status'  => 'pending',
                'delivery_address' => $address,
                'latitude'         => $lat,
                'longitude'        => $lng,
            ]);

            $this->db->commit();
        } catch (RuntimeException $e) {
            $this->db->roll_back();
            $this->api->respond_error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            $this->db->roll_back();
            $this->api->respond_error('Hindi natuloy ang order. Subukan ulit.', 500);
        }

        $this->api->respond([
            'message'         => 'Na-create ang order.',
            'order_id'        => (int) $orderId,
            'subtotal'        => $subtotal,
            'delivery_method' => $method,
            'delivery_fee'    => $deliveryFee,
            'total_amount'    => $total,
        ]);
    }

    /**
     * POST /api/orders/pay
     * Body: order_id
     * Simulated e-wallet: agad na nagiging paid ang bayad at confirmed ang order.
     */
    public function pay()
    {
        $this->api->require_method('POST');
        $buyerId = api_require_role($this->api, 'buyer', 'Buyer account lang ang puwedeng mag-order.');
        $body = $this->api->body();

        $orderId = (int) ($body['order_id'] ?? 0);
        if ($orderId <= 0) {
            $this->api->respond_error('Kailangan ang order_id.', 422);
        }

        $order = $this->order->_find($orderId);
        if (!$order) {
            $this->api->respond_error('Hindi mahanap ang order.', 404);
        }
        $order = (array) $order;

        if ((int) $order['buyer_id'] !== $buyerId) {
            $this->api->respond_error('Hindi mahanap ang order.', 404);
        }
        if ($order['status'] !== 'pending') {
            $this->api->respond_error('Hindi na puwedeng bayaran ang order na ito.', 409);
        }

        $existing = $this->payment->_find_all_by('order_id', $orderId) ?: [];
        foreach ($existing as $p) {
            $p = (array) $p;
            if ($p['payment_status'] === 'paid') {
                $this->api->respond_error('Bayad na ang order na ito.', 409);
            }
        }

        $reference = 'SIM-' . strtoupper(bin2hex(random_bytes(5)));

        $this->payment->_insert([
            'order_id'              => $orderId,
            'amount'                => $order['total_amount'],
            'payment_method'        => 'gcash',
            'payment_status'        => 'paid',
            'transaction_reference' => $reference,
            'paid_at'               => date('Y-m-d H:i:s'),
        ]);

        $this->order->_update($orderId, ['status' => 'confirmed']);

        $this->api->respond([
            'message'               => 'Bayad na.',
            'order_id'              => $orderId,
            'transaction_reference' => $reference,
        ]);
    }

    /**
     * POST /api/orders/cancel
     * Body: order_id
     * Pending pa lang ang puwedeng i-cancel. Ibinabalik ang stock ng mga produkto.
     */
    public function cancel()
    {
        $this->api->require_method('POST');
        $buyerId = api_require_role($this->api, 'buyer', 'Buyer account lang ang puwedeng mag-order.');
        $body = $this->api->body();

        $orderId = (int) ($body['order_id'] ?? 0);
        if ($orderId <= 0) {
            $this->api->respond_error('Kailangan ang order_id.', 422);
        }

        $order = $this->order->_find($orderId);
        if (!$order) {
            $this->api->respond_error('Hindi mahanap ang order.', 404);
        }
        $order = (array) $order;

        if ((int) $order['buyer_id'] !== $buyerId) {
            $this->api->respond_error('Hindi mahanap ang order.', 404);
        }
        if ($order['status'] !== 'pending') {
            $this->api->respond_error('Hindi na puwedeng i-cancel ang order na ito.', 409);
        }

        $items = $this->db->table('order_items')->where('order_id', $orderId)->get_all() ?: [];

        // Transaction: ibalik ang stock, i-cancel ang order at delivery, lahat o wala
        $this->db->transaction();
        try {
            foreach ($items as $item) {
                $item = (array) $item;

                $product = $this->product->_find((int) $item['product_id']);
                if (!$product) {
                    continue;
                }
                $product = (array) $product;

                $wasOutOfStock = (int) $product['quantity_available'] === 0;
                $update = [
                    'quantity_available' => (int) $product['quantity_available'] + (int) $item['quantity'],
                ];

                // Kung naging unavailable dahil naubos ang stock, ibalik sa available
                if ($wasOutOfStock && $product['availability'] === 'unavailable') {
                    $update['availability'] = 'available';
                }

                $this->product->_update((int) $product['product_id'], $update);
            }

            $this->order->_update($orderId, ['status' => 'cancelled']);

            $this->db->table('deliveries')
                ->where('order_id', $orderId)
                ->update(['delivery_status' => 'cancelled']);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->roll_back();
            $this->api->respond_error('Hindi natuloy ang cancel. Subukan ulit.', 500);
        }

        $this->api->respond([
            'message'  => 'Na-cancel ang order.',
            'order_id' => $orderId,
            'status'   => 'cancelled',
        ]);
    }
}