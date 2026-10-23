<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class BuyerCart extends Controller {

	private function requireBuyer() {
		$this->call->library('session');
		if (!$this->session->userdata('user_id')) {
			header('Location: /login');
			exit;
		}
		if ($this->session->userdata('role') !== 'buyer') {
			header('Location: /dashboard');
			exit;
		}
	}

	private function loadProductModel() {
		$this->call->database();
		$this->call->model('Product', 'product');
	}

	private function getCart() {
		$cart = $this->session->userdata('cart');
		return is_array($cart) ? $cart : [];
	}

	private function saveCart($cart) {
		$this->session->set_userdata('cart', $cart);
	}

	public function index() {
		$this->requireBuyer();
		$this->loadProductModel();

		$items = [];
		$total = 0;
		foreach ($this->getCart() as $product_id => $qty) {
			$product = $this->product->_find((int) $product_id);
			if (!$product) {
				continue;
			}
			$subtotal = (float) $product['price'] * (int) $qty;
			$total += $subtotal;
			$items[] = [
				'product'  => $product,
				'quantity' => (int) $qty,
				'subtotal' => $subtotal,
			];
		}

		$this->call->view('buyer_cart', ['items' => $items, 'total' => $total]);
	}

	public function add() {
		$this->requireBuyer();
		$this->loadProductModel();

		$product_id = (int) $this->io->post('product_id');
		$qty        = max(1, (int) $this->io->post('quantity'));
		$product    = $this->product->_find($product_id);

		if ($product && $product['availability'] === 'available' && (int) $product['quantity_available'] > 0) {
			$cart = $this->getCart();
			$current = isset($cart[$product_id]) ? (int) $cart[$product_id] : 0;
			$cart[$product_id] = min($current + $qty, (int) $product['quantity_available']);
			$this->saveCart($cart);
		}

		header('Location: /buyer/cart');
		exit;
	}

	public function update() {
		$this->requireBuyer();
		$this->loadProductModel();

		$product_id = (int) $this->io->post('product_id');
		$qty        = (int) $this->io->post('quantity');
		$product    = $this->product->_find($product_id);
		$cart       = $this->getCart();

		if ($product && isset($cart[$product_id])) {
			if ($qty < 1) {
				unset($cart[$product_id]);
			} else {
				$cart[$product_id] = min($qty, (int) $product['quantity_available']);
			}
			$this->saveCart($cart);
		}

		header('Location: /buyer/cart');
		exit;
	}

	public function remove() {
		$this->requireBuyer();

		$product_id = (int) $this->io->post('product_id');
		$cart = $this->getCart();
		unset($cart[$product_id]);
		$this->saveCart($cart);

		header('Location: /buyer/cart');
		exit;
	}
}
?>