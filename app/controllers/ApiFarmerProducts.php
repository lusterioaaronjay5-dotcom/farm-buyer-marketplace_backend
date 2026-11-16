<?php

class ApiFarmerProducts extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
        $this->call->helper('farmer');
        $this->call->model('Product', 'product');
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
     * Kinukuha ang address ng farmer mula sa users table, gagamitin bilang lokasyon ng produkto.
     */
    private function farmerAddress($farmerId)
    {
        $rows = $this->db->table('users')->select('address')->where('user_id', $farmerId)->get_all() ?: [];
        if (empty($rows)) {
            return null;
        }

        $row = (array) $rows[0];
        $address = trim((string) ($row['address'] ?? ''));

        return $address !== '' ? mb_substr($address, 0, 255) : null;
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
        $imagePath    = trim($body['image_path'] ?? '');
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

        // Larawan: naka-encode na JPEG, PNG, o WEBP lang, at may limit na laki
        if ($imagePath !== '') {
            if (!preg_match('#^data:image/(jpeg|png|webp);base64,#', $imagePath)) {
                $errors[] = 'Image must be a JPG, PNG, or WEBP file.';
            } elseif (strlen($imagePath) > 500000) {
                $errors[] = 'Image is too large. Pumili ng mas maliit na larawan.';
            }
        }

        return [
            'errors' => $errors,
            'data'   => [
                'product_name'       => $name,
                'category'           => $category,
                'unit'               => $unit,
                'description'        => $description !== '' ? $description : null,
                'location'           => $location !== '' ? $location : null,
                'image_path'         => $imagePath !== '' ? $imagePath : null,
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
        $farmerId = farmer_require_farmer($this->api);

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
        $farmerId = farmer_require_farmer($this->api);
        $body = $this->api->body();

        $result = $this->validateInput($body);
        if (!empty($result['errors'])) {
            $this->api->respond_error($result['errors'][0], 422);
        }

        $data = $result['data'];
        $data['farmer_id'] = $farmerId;

        // Walang lokasyon sa form: gamitin ang address ng farmer
        if ($data['location'] === null) {
            $data['location'] = $this->farmerAddress($farmerId);
        }

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
        $farmerId = farmer_require_farmer($this->api);
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

        $data = $result['data'];

        // Kung walang lokasyon, gamitin ang address ng farmer
        if ($data['location'] === null) {
            $data['location'] = $this->farmerAddress($farmerId);
        }

        $this->product->_update($productId, $data);
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
        $farmerId = farmer_require_farmer($this->api);
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