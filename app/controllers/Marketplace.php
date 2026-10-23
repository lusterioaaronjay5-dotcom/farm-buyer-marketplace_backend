<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Marketplace extends Controller {
	public function index() {
		$this->call->database();
		$this->call->model('Product', 'product');
		$products = $this->product->_all();
		$this->call->view('marketplace', ['products' => $products]);
	}
}
?>