<?php

class ApiFarmerProducts extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->model('Product', 'product');
    }

    /**
     * Ginagawang array ang anumang hugis ng decoded token (array, object, string, o nakabalot sa "data").
     */
    private function toArray($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (is_array($value) && isset($value['data']) && (is_array($value['data']) || is_object($value['data']))) {
            $value = (array) $value['data'];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * Sinisiguro na may valid token at farmer ang nag-request. Ibinabalik ang user_id.
     */
    private function requireFarmer()
    {
        $token = $this->api->get_bearer_token();
        if (!$token) {
            $this->api->respond_error('Missing token.', 401);
        }

        try {
            $decoded = $this->api->validate_jwt($token);
        } catch (\Throwable $e) {
            $this->api->respond_error('Token error: ' . $e->getMessage(), 401);
        }

        $payload = $this->toArray($decoded);
        if (empty($payload['user_id'])) {
            $this->api->respond_error(
                'Invalid or expired token. validate_jwt returned: ' . gettype($decoded),
                401
            );
        }

        if (($payload['role'] ?? '') !== 'farmer') {
            $this->api->respond_error('Farmers lang ang puwedeng gumawa nito.', 403);
        }

        return (int) $payload['user_id'];
    }

    private function formatProduct(array $p)
    {
        return [
            'product_id'         => (int) $p['product_id'],
            'farmer_id'          => (int) $p['farmer_id'],
            'product_name'       => $p['product_name'],
            'description'        => $p['description'] ?? null,
            'category'           => $p['category'],
            'price'              => (float) $p['price'],
            'quantity_available' => (int) $p['quantity_available'],
            'unit'               => $p['unit'],
            'availability'       => $p['availability'],
            'location'           => $p['location'] ?? null,
            'image_path'         => $p['image_path'] ?? null,
            'created_at'         => $p['created_at'] ?? null,
            'updated_at'         => $p['updated_at'] ?? null,
        ];
    }

    /**
     * Binabasa ang input at ibinabalik ang listahan ng error at malinis na data.
     */
    private function validateInput(array $body)
    {
        $errors = [];

        $name         = trim($body['product_name'] ?? '');
        $category     = trim($body['category'] ?? '');
        $unit         = trim($body['unit'] ?? '');
        $description  = trim($body['description'] ?? '');
        $location     = trim($body['location'] ?? '');
        $availability = $body['availability'] ?? 'available';
        $price        = $body['price'] ?? null;
        $quantity     = $body['quantity_available'] ?? null;

        if ($name === '') {
            $errors[] = 'Product name is required.';
        } elseif (strlen($name) > 150) {
            $errors[] = 'Product name must be 150 characters or less.';
        }

        if ($category === '') {
            $errors[] = 'Category is required.';
        } elseif (strlen($category) > 100) {
            $errors[] = 'Category must be 100 characters or less.';
        }

        if ($unit === '') {
            $errors[] = 'Unit is required (halimbawa: kg, bundle, piece).';
        } elseif (strlen($unit) > 30) {
            $errors[] = 'Unit must be 30 characters or less.';
        }

        if ($price === null || filter_var($price, FILTER_VALIDATE_FLOAT) === false || (float) $price < 0) {
            $errors[] = 'Price must be a number, zero or higher.';
        }

        if ($quantity === null || filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
            $errors[] = 'Quantity must be a whole number, zero or higher.';
        }

        if (!in_array($availability, ['available', 'unavailable'], true)) {
            $errors[] = 'Availability must be available or unavailable.';
        }

        if (strlen($location) > 255) {
            $errors[] = 'Location must be 255 characters or less.';
        }

        return [
            'errors' => $errors,
            'data'   => [
                'product_name'       => $name,
                'category'           => $category,
                'unit'               => $unit,
                'description'        => $description !== '' ? $description : null,
                'location'           => $location !== '' ? $location : null,
                'availability'       => $availability,
                'price'              => round((float) $price, 2),
                'quantity_available' => (int) $quantity,
            ],
        ];
    }

    /**
     * Kumukuha ng produkto at sinisiguro na sa farmer na naka-login ito.
     */
    private function ownedProduct($productId, $farmerId)
    {
        $product = $this->product->_find((int) $productId);
        if (!$product) {
            $this->api->respond_error('Product not found.', 404);
        }

        $product = (array) $product;
        if ((int) $product['farmer_id'] !== $farmerId) {
            $this->api->respond_error('Product not found.', 404);
        }

        return $product;
    }

    /**
     * GET /api/farmer/products
     */
    public function index()
    {
        $this->api->require_method('GET');
        $farmerId = $this->requireFarmer();

        $rows = $this->product->_find_all_by('farmer_id', $farmerId) ?: [];
        $products = array_map(fn ($r) => $this->formatProduct((array) $r), $rows);

        $this->api->respond(['products' => $products]);
    }

    /**
     * POST /api/farmer/products
     */
    public function store()
    {
        $this->api->require_method('POST');
        $farmerId = $this->requireFarmer();
        $body = $this->api->body();

        $result = $this->validateInput($body);
        if (!empty($result['errors'])) {
            $this->api->respond_error($result['errors'][0], 422);
        }

        $data = $result['data'];
        $data['farmer_id'] = $farmerId;

        $id = $this->product->_insert($data);
        $product = $this->product->_find($id);

        $this->api->respond([
            'message' => 'Product added.',
            'product' => $this->formatProduct((array) $product),
        ]);
    }

    /**
     * POST /api/farmer/products/update
     * Body: product_id + mga field na papalitan
     */
    public function update()
    {
        $this->api->require_method('POST');
        $farmerId = $this->requireFarmer();
        $body = $this->api->body();

        $productId = (int) ($body['product_id'] ?? 0);
        if ($productId <= 0) {
            $this->api->respond_error('product_id is required.', 422);
        }

        $this->ownedProduct($productId, $farmerId);

        $result = $this->validateInput($body);
        if (!empty($result['errors'])) {
            $this->api->respond_error($result['errors'][0], 422);
        }

        $this->product->_update($productId, $result['data']);
        $product = $this->product->_find($productId);

        $this->api->respond([
            'message' => 'Product updated.',
            'product' => $this->formatProduct((array) $product),
        ]);
    }

    /**
     * POST /api/farmer/products/delete
     * Body: product_id
     */
    public function destroy()
    {
        $this->api->require_method('POST');
        $farmerId = $this->requireFarmer();
        $body = $this->api->body();

        $productId = (int) ($body['product_id'] ?? 0);
        if ($productId <= 0) {
            $this->api->respond_error('product_id is required.', 422);
        }

        $this->ownedProduct($productId, $farmerId);

        $deleted = $this->product->_delete($productId);
        if ($deleted === false) {
            $this->api->respond_error(
                'Hindi ma-delete ang produktong may order na. Gawing unavailable na lang.',
                409
            );
        }

        $this->api->respond(['message' => 'Product deleted.']);
    }
}