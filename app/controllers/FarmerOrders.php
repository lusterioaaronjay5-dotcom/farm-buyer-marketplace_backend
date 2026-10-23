<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class FarmerOrders extends Controller {

	private $statuses = ['pending', 'confirmed', 'preparing', 'out_for_delivery', 'completed', 'cancelled'];

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

	private function loadModels() {
		$this->call->database();
		$this->call->model('Product', 'product');
		$this->call->model('Order', 'order');
		$this->call->model('OrderItem', 'order_item');
		$this->call->model('User', 'user');
	}

	private function farmerHasOrder($order_id, $farmer_id) {
		foreach ($this->order_item->_find_all_by('order_id', $order_id) ?: [] as $item) {
			$product = $this->product->_find((int) $item['product_id']);
			if ($product && (int) $product['farmer_id'] === $farmer_id) {
				return true;
			}
		}
		return false;
	}

	public function index() {
		$farmer_id = $this->requireFarmer();
		$this->loadModels();

		$products = $this->product->_find_all_by('farmer_id', $farmer_id) ?: [];

		$byOrder = [];
		foreach ($products as $product) {
			$pid = (int) $product['product_id'];
			foreach ($this->order_item->_find_all_by('product_id', $pid) ?: [] as $item) {
				$byOrder[(int) $item['order_id']][] = [
					'name'       => $product['product_name'],
					'quantity'   => (int) $item['quantity'],
					'unit_price' => $item['unit_price'],
					'subtotal'   => (float) $item['subtotal'],
				];
			}
		}

		$rows = [];
		foreach ($byOrder as $order_id => $entries) {
			$order = $this->order->_find((int) $order_id);
			if (!$order) {
				continue;
			}
			$buyer = $this->user->_find((int) $order['buyer_id']);
			$farmer_total = 0;
			foreach ($entries as $e) {
				$farmer_total += $e['subtotal'];
			}
			$rows[] = [
				'order'        => $order,
				'buyer'        => $buyer,
				'entries'      => $entries,
				'farmer_total' => $farmer_total,
			];
		}

		usort($rows, function ($a, $b) {
			return (int) $b['order']['order_id'] - (int) $a['order']['order_id'];
		});

		$this->call->view('farmer_orders', [
			'rows'     => $rows,
			'statuses' => $this->statuses,
		]);
	}

	public function updateStatus() {
		$farmer_id = $this->requireFarmer();
		$this->loadModels();

		$order_id = (int) $this->io->post('order_id');
		$status   = (string) $this->io->post('status');

		if (in_array($status, $this->statuses, true) && $this->farmerHasOrder($order_id, $farmer_id)) {
			$this->order->_update($order_id, ['status' => $status]);
		}

		header('Location: /farmer/orders');
		exit;
	}
}
?>