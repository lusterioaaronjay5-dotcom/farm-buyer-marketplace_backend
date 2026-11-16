<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiReviews extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
    }

    // Hinahanap ang order at sinisigurong sa buyer na ito
    private function findOwnedOrder($orderId, $buyerId)
    {
        $rows = $this->db->table('orders')->where('order_id', $orderId)->get_all() ?: [];
        if (empty($rows)) {
            $this->api->respond_error('Hindi mahanap ang order.', 404);
        }

        $order = (array) $rows[0];
        if ((int) $order['buyer_id'] !== $buyerId) {
            $this->api->respond_error('Hindi mahanap ang order.', 404);
        }

        return $order;
    }

    // Kinukuha lang ang unang pangalan para hindi ipakita ang buong pangalan ng buyer
    private function firstName($name)
    {
        $parts = preg_split('/\s+/', trim((string) $name));
        return $parts[0] ?: 'Buyer';
    }

    /**
     * POST /api/reviews
     * Body: order_id, product_id, rating (1-5), comment (opsyonal)
     * Buyer lang, at completed na ang order. Isang rating lang kada produkto sa bawat order.
     */
    public function store()
    {
        $this->api->require_method('POST');
        $buyerId = api_require_role($this->api, 'buyer', 'Buyer account lang ang puwedeng mag-rate.');
        $body = $this->api->body();

        $orderId   = (int) ($body['order_id'] ?? 0);
        $productId = (int) ($body['product_id'] ?? 0);
        $rating    = (int) ($body['rating'] ?? 0);
        $comment   = trim((string) ($body['comment'] ?? ''));

        if ($orderId <= 0 || $productId <= 0) {
            $this->api->respond_error('Kailangan ang order_id at product_id.', 422);
        }
        if ($rating < 1 || $rating > 5) {
            $this->api->respond_error('Dapat 1 hanggang 5 ang rating.', 422);
        }
        if (mb_strlen($comment) > 1000) {
            $this->api->respond_error('Masyadong mahaba ang feedback (max 1000 characters).', 422);
        }

        $order = $this->findOwnedOrder($orderId, $buyerId);

        if ($order['status'] !== 'completed') {
            $this->api->respond_error('Puwede ka lang mag-rate kapag natanggap na ang order.', 409);
        }

        $inOrder = $this->db->table('order_items')
            ->where('order_id', $orderId)
            ->where('product_id', $productId)
            ->get_all() ?: [];
        if (empty($inOrder)) {
            $this->api->respond_error('Hindi kasama sa order na ito ang produkto.', 422);
        }

        $existing = $this->db->table('reviews')
            ->where('order_id', $orderId)
            ->where('product_id', $productId)
            ->where('buyer_id', $buyerId)
            ->get_all() ?: [];
        if (!empty($existing)) {
            $this->api->respond_error('Na-rate mo na ang produktong ito.', 409);
        }

        $this->db->table('reviews')->insert([
            'order_id'   => $orderId,
            'product_id' => $productId,
            'buyer_id'   => $buyerId,
            'rating'     => $rating,
            'comment'    => $comment !== '' ? $comment : null,
        ]);

        $this->api->respond(['message' => 'Salamat sa feedback!']);
    }

    /**
     * GET /api/reviews/order?order_id=
     * Mga rating na ginawa ng buyer para sa isang order.
     */
    public function order_reviews()
    {
        $this->api->require_method('GET');
        $buyerId = api_require_role($this->api, 'buyer', 'Buyer account lang ang puwedeng mag-rate.');

        $orderId = (int) ($_GET['order_id'] ?? 0);
        if ($orderId <= 0) {
            $this->api->respond_error('Kailangan ang order_id.', 422);
        }
        $this->findOwnedOrder($orderId, $buyerId);

        $rows = $this->db->table('reviews')
            ->where('order_id', $orderId)
            ->where('buyer_id', $buyerId)
            ->get_all() ?: [];

        $reviews = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            $reviews[] = [
                'product_id' => (int) $r['product_id'],
                'rating'     => (int) $r['rating'],
                'comment'    => $r['comment'] ?? null,
            ];
        }

        $this->api->respond(['reviews' => $reviews]);
    }

    /**
     * GET /api/reviews/product?product_id=
     * Public: average rating, bilang ng rating, at mga feedback ng isang produkto.
     */
    public function product_reviews()
    {
        $this->api->require_method('GET');

        $productId = (int) ($_GET['product_id'] ?? 0);
        if ($productId <= 0) {
            $this->api->respond_error('Kailangan ang product_id.', 422);
        }

        $rows = $this->db->table('reviews')
            ->where('product_id', $productId)
            ->get_all() ?: [];

        $buyerIds = [];
        foreach ($rows as $row) {
            $buyerIds[] = (int) ((array) $row)['buyer_id'];
        }
        $buyers = api_key_by(
            api_fetch_in($this->db, 'users', 'user_id', array_values(array_unique($buyerIds))),
            'user_id'
        );

        $reviews = [];
        $sum = 0;
        foreach ($rows as $row) {
            $r = (array) $row;
            $sum += (int) $r['rating'];

            $reviews[] = [
                'rating'     => (int) $r['rating'],
                'comment'    => $r['comment'] ?? null,
                'buyer_name' => $this->firstName($buyers[(int) $r['buyer_id']]['full_name'] ?? ''),
                'created_at' => $r['created_at'] ?? null,
            ];
        }

        usort($reviews, fn ($a, $b) => strcmp((string) $b['created_at'], (string) $a['created_at']));

        $count = count($reviews);
        $this->api->respond([
            'average' => $count > 0 ? round($sum / $count, 1) : null,
            'count'   => $count,
            'reviews' => $reviews,
        ]);
    }
}