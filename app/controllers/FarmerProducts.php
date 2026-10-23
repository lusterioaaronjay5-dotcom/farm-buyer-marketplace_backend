<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class FarmerProducts extends Controller {

	private function requireFarmer() {
		$this->call->library('session');
		if (!$this->session->userdata('user_id')) {
			header('Location: /login');
			exit;
		}
		if ($this->session->userdata('role') !== 'farmer') {
			header('Location: /dashboard');
			exit;
		}
		return (int) $this->session->userdata('user_id');
	}

	private function loadProductModel() {
		$this->call->database();
		$this->call->model('Product', 'product');
	}

	private function readForm() {
		return [
			'product_name'       => trim((string) $this->io->post('product_name')),
			'description'        => trim((string) $this->io->post('description')),
			'category'           => trim((string) $this->io->post('category')),
			'price'              => (string) $this->io->post('price'),
			'quantity_available' => (string) $this->io->post('quantity_available'),
			'unit'               => trim((string) $this->io->post('unit')),
			'availability'       => (string) $this->io->post('availability'),
			'location'           => trim((string) $this->io->post('location')),
		];
	}

	private function validateForm($d) {
		if ($d['product_name'] === '' || $d['category'] === '' || $d['unit'] === '') {
			return 'Product name, category, and unit are required.';
		}
		if (!is_numeric($d['price']) || (float) $d['price'] < 0) {
			return 'Price must be a valid positive number.';
		}
		if (!ctype_digit($d['quantity_available'])) {
			return 'Quantity must be a whole number.';
		}
		if (!in_array($d['availability'], ['available', 'unavailable'], true)) {
			return 'Please select availability.';
		}
		return null;
	}

	private function ownedProduct($product_id, $farmer_id) {
		$product = $this->product->_find((int) $product_id);
		if (!$product || (int) $product['farmer_id'] !== $farmer_id) {
			header('Location: /farmer/products');
			exit;
		}
		return $product;
	}

	public function index() {
		$farmer_id = $this->requireFarmer();
		$this->loadProductModel();
		$products = $this->product->_find_all_by('farmer_id', $farmer_id);
		$this->call->view('farmer_products', ['products' => $products]);
	}

	public function addForm() {
		$this->requireFarmer();
		$this->call->view('farmer_product_form', [
			'mode' => 'add', 'product' => null, 'error' => null,
		]);
	}

	public function addSubmit() {
		$farmer_id = $this->requireFarmer();
		$data = $this->readForm();
		$error = $this->validateForm($data);

		if ($error) {
			return $this->call->view('farmer_product_form', [
				'mode' => 'add', 'product' => $data, 'error' => $error,
			]);
		}

		$this->loadProductModel();
		$data['farmer_id'] = $farmer_id;
		$this->product->_insert($data);

		header('Location: /farmer/products');
		exit;
	}

	public function editForm() {
		$farmer_id = $this->requireFarmer();
		$this->loadProductModel();
		$product = $this->ownedProduct($this->io->get('id'), $farmer_id);

		$this->call->view('farmer_product_form', [
			'mode' => 'edit', 'product' => $product, 'error' => null,
		]);
	}

	public function editSubmit() {
		$farmer_id = $this->requireFarmer();
		$this->loadProductModel();
		$product_id = (int) $this->io->post('product_id');
		$this->ownedProduct($product_id, $farmer_id);

		$data = $this->readForm();
		$error = $this->validateForm($data);

		if ($error) {
			$data['product_id'] = $product_id;
			return $this->call->view('farmer_product_form', [
				'mode' => 'edit', 'product' => $data, 'error' => $error,
			]);
		}

		$this->product->_update($product_id, $data);

		header('Location: /farmer/products');
		exit;
	}

	public function delete() {
		$farmer_id = $this->requireFarmer();
		$this->loadProductModel();
		$product_id = (int) $this->io->post('product_id');
		$this->ownedProduct($product_id, $farmer_id);

		$this->product->_delete($product_id);

		header('Location: /farmer/products');
		exit;
	}
}
?>