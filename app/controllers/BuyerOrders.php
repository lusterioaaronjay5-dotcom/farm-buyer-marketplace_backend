<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class BuyerOrders extends Controller {

	public function index() {
		$this->call->library('session');

		if (!$this->session->userdata('user_id')) {
			header('Location: /login');
			exit;
		}
		if ($this->session->userdata('role') !== 'buyer') {
			header('Location: /dashboard');
			exit;
		}
		$buyer_id = (int) $this->session->userdata('user_id');

		$this->call->database();
		$this->call->model('Order', 'order');
		$this->call->model('OrderItem', 'order_item');
		$this->call->model('Product', 'product');
		$this->call->model('Payment', 'payment');
		$this->call->model('Delivery', 'delivery');

		$orders = $this->order->_find_all_by('buyer_id', $buyer_id);
		usort($orders, function ($a, $b) {
			return (int) $b['order_id'] - (int) $a['order_id'];
		});

		$rows = [];
		foreach ($orders as $order) {
			$oid = (int) $order['order_id'];

			$items = [];
			foreach ($this->order_item->_find_all_by('order_id', $oid) as $item) {
				$product = $this->product->_find((int) $item['product_id']);
				$items[] = [
					'name'       => $product ? $product['product_name'] : 'Deleted product',
					'quantity'   => (int) $item['quantity'],
					'unit_price' => $item['unit_price'],
					'subtotal'   => $item['subtotal'],
				];
			}

			$payment  = $this->payment->_find_by('order_id', $oid);
			$delivery = $this->delivery->_find_by('order_id', $oid);

			$rows[] = [
				'order'    => $order,
				'items'    => $items,
				'payment'  => $payment,
				'delivery' => $delivery,
			];
		}

		$this->call->view('buyer_orders', ['rows' => $rows]);
	}
}
?>