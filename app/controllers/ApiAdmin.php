<?php

class ApiAdmin extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
        $this->call->model('Product', 'product');
    }

    // Mga field lang na ligtas ipakita sa admin (walang password_hash)
    private function pickUser(array $u)
    {
        return [
            'user_id'   => (int) $u['user_id'],
            'full_name' => $u['full_name'] ?? null,
            'email'     => $u['email'] ?? null,
            'role'      => $u['role'] ?? null,
            'phone'     => $u['phone'] ?? null,
            'address'   => $u['address'] ?? null,
        ];
    }

    private function pickProduct(array $p, array $farmers)
    {
        $farmer = $farmers[(int) $p['farmer_id']] ?? [];
        return [
            'product_id'         => (int) $p['product_id'],
            'farmer_id'          => (int) $p['farmer_id'],
            'farmer_name'        => $farmer['full_name'] ?? null,
            'product_name'       => $p['product_name'] ?? null,
            'category'           => $p['category'] ?? null,
            'price'              => (float) ($p['price'] ?? 0),
            'quantity_available' => (int) ($p['quantity_available'] ?? 0),
            'unit'               => $p['unit'] ?? null,
            'availability'       => $p['availability'] ?? null,
            'location'           => $p['location'] ?? null,
            'created_at'         => $p['created_at'] ?? null,
        ];
    }

    // GET /api/admin/users
    public function users()
    {
        $this->api->require_method('GET');
        api_require_role($this->api, 'admin', 'Admin lang ang puwedeng gumamit nito.');

        $rows = $this->db->table('users')->get_all() ?: [];

        $users = [];
        foreach ($rows as $row) {
            $users[] = $this->pickUser((array) $row);
        }
        usort($users, fn ($a, $b) => $b['user_id'] <=> $a['user_id']);

        $this->api->respond(['users' => $users]);
    }

    // POST /api/admin/user-role   body: { user_id, role }
    public function user_role()
    {
        $this->api->require_method('POST');
        $adminId = (int) api_require_role($this->api, 'admin', 'Admin lang ang puwedeng gumamit nito.');

        $body   = $this->api->body();
        $userId = (int) ($body['user_id'] ?? 0);
        $role   = trim((string) ($body['role'] ?? ''));

        if (!in_array($role, ['buyer', 'farmer', 'admin'], true)) {
            $this->api->respond_error('Hindi valid ang role.', 422);
        }

        // Hindi puwedeng tanggalin ang sarili mong admin role
        if ($userId === $adminId && $role !== 'admin') {
            $this->api->respond_error('Hindi mo puwedeng baguhin ang sarili mong role.', 422);
        }

        $rows = $this->db->table('users')->where('user_id', $userId)->get_all() ?: [];
        if (empty($rows)) {
            $this->api->respond_error('Hindi nahanap ang user.', 404);
        }

        $this->db->table('users')->where('user_id', $userId)->update(['role' => $role]);

        $this->api->respond(['message' => 'Na-update ang role.']);
    }

    // GET /api/admin/products
    public function products()
    {
        $this->api->require_method('GET');
        api_require_role($this->api, 'admin', 'Admin lang ang puwedeng gumamit nito.');

        $rows = $this->db->table('products')->get_all() ?: [];

        $farmerIds = array_values(array_unique(array_map(
            fn ($r) => (int) ((array) $r)['farmer_id'],
            $rows
        )));
        $farmers = api_key_by(api_fetch_in($this->db, 'users', 'user_id', $farmerIds), 'user_id');

        $products = [];
        foreach ($rows as $row) {
            $products[] = $this->pickProduct((array) $row, $farmers);
        }
        usort($products, fn ($a, $b) => $b['product_id'] <=> $a['product_id']);

        $this->api->respond(['products' => $products]);
    }

    // POST /api/admin/product-availability   body: { product_id, availability }
    public function product_availability()
    {
        $this->api->require_method('POST');
        api_require_role($this->api, 'admin', 'Admin lang ang puwedeng gumamit nito.');

        $body      = $this->api->body();
        $productId = (int) ($body['product_id'] ?? 0);
        $status    = trim((string) ($body['availability'] ?? ''));

        if (!in_array($status, ['available', 'unavailable'], true)) {
            $this->api->respond_error('Hindi valid ang availability.', 422);
        }

        $product = $this->product->_find($productId);
        if (!$product) {
            $this->api->respond_error('Hindi nahanap ang product.', 404);
        }

        $this->product->_update($productId, ['availability' => $status]);

        $this->api->respond(['message' => 'Na-update ang product.']);
    }
}