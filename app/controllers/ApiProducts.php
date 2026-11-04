<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiProducts extends Controller {

    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
        $this->call->model('Product', 'product');
        $this->call->model('User', 'user');
    }

    private function formatProduct($p, $farmer_name)
    {
        return [
            'product_id'         => (int) $p['product_id'],
            'product_name'       => $p['product_name'],
            'description'        => $p['description'] ?? '',
            'category'           => $p['category'],
            'price'              => (float) $p['price'],
            'unit'               => $p['unit'] ?? '',
            'quantity_available' => (int) $p['quantity_available'],
            'availability'       => $p['availability'],
            'location'           => $p['location'] ?? '',
            'farmer_id'          => (int) $p['farmer_id'],
            'farmer_name'        => $farmer_name,
        ];
    }

    /**
     * GET /api/products
     * Dalawang query lang: lahat ng produkto, at ang mga farmer lang na may produkto.
     */
    public function index()
    {
        $this->api->require_method('GET');

        $products = array_map(fn ($p) => (array) $p, $this->product->_all() ?: []);

        $farmerIds = array_map(fn ($p) => (int) $p['farmer_id'], $products);
        $farmers = [];
        foreach (api_fetch_in($this->db, 'users', 'user_id', $farmerIds) as $u) {
            $u = (array) $u;
            $farmers[(int) $u['user_id']] = $u['full_name'];
        }

        $out = [];
        foreach ($products as $p) {
            $out[] = $this->formatProduct($p, $farmers[(int) $p['farmer_id']] ?? 'Unknown');
        }

        $this->api->respond(['products' => $out]);
    }

    /**
     * GET /api/products/detail?id=
     */
    public function show()
    {
        $this->api->require_method('GET');

        $p = $this->product->_find((int) $this->io->get('id'));
        if (!$p) {
            $this->api->respond_error('Product not found.', 404);
        }

        $p = (array) $p;
        $farmer = $this->user->_find((int) $p['farmer_id']);
        $farmer = $farmer ? (array) $farmer : [];

        $this->api->respond(['product' => $this->formatProduct($p, $farmer['full_name'] ?? 'Unknown')]);
    }
}