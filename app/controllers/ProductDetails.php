<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductDetails extends Controller {
	public function index() {
		$this->call->library('session');

		if (!$this->session->userdata('user_id')) {
			header('Location: /login');
			exit;
		}

		$this->call->database();
		$this->call->model('Product', 'product');
		$this->call->model('User', 'user');

		$product = $this->product->_find((int) $this->io->get('id'));
		if (!$product) {
			header('Location: /marketplace');
			exit;
		}

		$farmer = $this->user->_find((int) $product['farmer_id']);

		$this->call->view('product_details', [
			'product' => $product,
			'farmer'  => $farmer,
			'role'    => $this->session->userdata('role'),
		]);
	}
}
?>