<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiFarmers extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
    }

    /**
     * GET /api/farmers/detail?id=FARMER_ID
     * Ibinabalik ang info ng farmer at ang mga produktong ibinebenta niya ngayon.
     */
    public function show()
    {
        api_require_user($this->api);

        $farmerId = (int) ($_GET['id'] ?? 0);
        if ($farmerId <= 0) {
            $this->api->respond_error('Kailangan ang id.', 422);
        }

        $rows = $this->db->table('users')
            ->select('user_id, full_name, email, phone, address, role')
            ->where('user_id', $farmerId)
            ->get_all() ?: [];

        $farmer = !empty($rows) ? (array) $rows[0] : null;
        if (!$farmer || $farmer['role'] !== 'farmer') {
            $this->api->respond_error('Hindi mahanap ang farmer.', 404);
        }

        // Lahat ng produkto ng farmer na ito
        $productRows = $this->db->table('products')
            ->where('farmer_id', $farmerId)
            ->get_all() ?: [];

        $products = [];
        foreach ($productRows as $row) {
            $p = (array) $row;
            $products[] = [
                'product_id'         => (int) $p['product_id'],
                'product_name'       => $p['product_name'],
                'category'           => $p['category'],
                'price'              => (float) $p['price'],
                'unit'               => $p['unit'] ?? '',
                'image_path'         => $p['image_path'] ?? null,
                'quantity_available' => (int) $p['quantity_available'],
                'availability'       => $p['availability'],
            ];
        }

        $this->api->respond([
            'farmer'   => $farmer,
            'products' => $products,
        ]);
    }
}