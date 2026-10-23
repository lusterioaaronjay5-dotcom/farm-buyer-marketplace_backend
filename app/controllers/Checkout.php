<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Checkout extends Controller {

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
		return (int) $this->session->userdata('user_id');
	}

	private function loadCartItems() {
		$this->call->database();
		$this->call->model('Product', 'product');

		$cart = $this->session->userdata('cart');
		$cart = is_array($cart) ? $cart : [];

		$items = [];
		$total = 0;
		foreach ($cart as $product_id => $qty) {
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
		return [$items, $total];
	}

	public function index() {
		$this->requireBuyer();
		list($items, $total) = $this->loadCartItems();

		if (empty($items)) {
			header('Location: /buyer/cart');
			exit;
		}

		$this->call->view('checkout', [
			'items'      => $items,
			'total'      => $total,
			'error'      => null,
			'old'        => [],
			'order_id'   => null,
		]);
	}

	public function place() {
		$user_id = $this->requireBuyer();
		list($items, $total) = $this->loadCartItems();

		if (empty($items)) {
			header('Location: /buyer/cart');
			exit;
		}

		$address = trim((string) $this->io->post('delivery_address'));
		$method  = (string) $this->io->post('payment_method');
		$old     = ['delivery_address' => $address, 'payment_method' => $method];

		$fail = function ($message) use ($items, $total, $old) {
			$this->call->view('checkout', [
				'items' => $items, 'total' => $total,
				'error' => $message, 'old' => $old, 'order_id' => null,
			]);
			exit;
		};

		if ($address === '') {
			$fail('Please enter a delivery address.');
		}
		if (!in_array($method, ['gcash', 'card'], true)) {
			$fail('Please select a payment method.');
		}
		foreach ($items as $item) {
			$p = $item['product'];
			if ($p['availability'] !== 'available' || (int) $p['quantity_available'] < $item['quantity']) {
				$fail('Not enough stock for ' . $p['product_name'] . '. Please update your cart.');
			}
		}

		$this->call->model('Order', 'order');
		$this->call->model('OrderItem', 'order_item');
		$this->call->model('Payment', 'payment');
		$this->call->model('Delivery', 'delivery');

		$this->order->_transaction();
		try {
			$order_id = $this->order->_insert([
				'buyer_id'         => $user_id,
				'order_date'       => date('Y-m-d H:i:s'),
				'status'           => 'pending',
				'total_amount'     => $total,
				'delivery_address' => $address,
			]);

			foreach ($items as $item) {
				$p = $item['product'];

				$this->order_item->_insert([
					'order_id'   => $order_id,
					'product_id' => (int) $p['product_id'],
					'quantity'   => $item['quantity'],
					'unit_price' => $p['price'],
					'subtotal'   => $item['subtotal'],
				]);

				$new_qty = (int) $p['quantity_available'] - $item['quantity'];
				$this->product->_update((int) $p['product_id'], [
					'quantity_available' => $new_qty,
					'availability'       => $new_qty > 0 ? 'available' : 'unavailable',
				]);
			}

			$this->payment->_insert([
				'order_id'              => $order_id,
				'amount'                => $total,
				'payment_method'        => $method,
				'payment_status'        => 'paid',
				'transaction_reference' => 'TEST-' . strtoupper(bin2hex(random_bytes(6))),
				'paid_at'               => date('Y-m-d H:i:s'),
			]);

			$this->delivery->_insert([
				'order_id'         => $order_id,
				'delivery_address' => $address,
				'delivery_status'  => 'pending',
			]);

			$this->order->_commit();
		} catch (Exception $e) {
			$this->order->_rollback();
			$fail('Something went wrong while placing your order. Please try again.');
		}

		$this->session->set_userdata('cart', []);

		$this->call->view('checkout', [
			'items' => [], 'total' => 0,
			'error' => null, 'old' => [], 'order_id' => $order_id,
		]);
	}
}
?>