<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiProducts extends Controller {

	private function formatProduct($p, $farmer_name) {
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
			'farmer_name'        => $farmer_name,
		];
	}

	public function index() {
		$this->call->library('api');
		$this->api->require_method('GET');

		$this->call->database();
		$this->call->model('Product', 'product');
		$this->call->model('User', 'user');

		$users = [];
		foreach ($this->user->_all() ?: [] as $u) {
			$users[(int) $u['user_id']] = $u['full_name'];
		}

		$out = [];
		foreach ($this->product->_all() ?: [] as $p) {
			$out[] = $this->formatProduct($p, $users[(int) $p['farmer_id']] ?? 'Unknown');
		}

		$this->api->respond(['products' => $out]);
	}

	public function show() {
		$this->call->library('api');
		$this->api->require_method('GET');

		$this->call->database();
		$this->call->model('Product', 'product');
		$this->call->model('User', 'user');

		$p = $this->product->_find((int) $this->io->get('id'));
		if (!$p) {
			$this->api->respond_error('Product not found.', 404);
		}

		$farmer = $this->user->_find((int) $p['farmer_id']);
		$this->api->respond(['product' => $this->formatProduct($p, $farmer['full_name'] ?? 'Unknown')]);
	}
}
?>