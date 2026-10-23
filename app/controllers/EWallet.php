<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class EWallet extends Controller {

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

	private function loadModels() {
		$this->call->database();
		$this->call->model('Order', 'order');
		$this->call->model('Payment', 'payment');
	}

	private function ownedOrder($order_id, $buyer_id) {
		$order = $this->order->_find($order_id);
		if (!$order || (int) $order['buyer_id'] !== $buyer_id) {
			header('Location: /buyer/orders');
			exit;
		}
		return $order;
	}

	public function index() {
		$buyer_id = $this->requireBuyer();
		$this->loadModels();

		$order_id = (int) $this->io->get('order_id');
		$order = $this->ownedOrder($order_id, $buyer_id);
		$payment = $this->payment->_find_by('order_id', $order_id);

		if (!$payment) {
			header('Location: /buyer/orders');
			exit;
		}

		$this->call->view('ewallet_pay', [
			'order'   => $order,
			'payment' => $payment,
		]);
	}

	public function confirm() {
		$buyer_id = $this->requireBuyer();
		$this->loadModels();

		$order_id = (int) $this->io->post('order_id');
		$this->ownedOrder($order_id, $buyer_id);

		$payment = $this->payment->_find_by('order_id', $order_id);
		if ($payment && $payment['payment_status'] !== 'paid') {
			$this->payment->_update_where(['order_id' => $order_id], [
				'payment_status' => 'paid',
				'paid_at'        => date('Y-m-d H:i:s'),
			]);
		}

		header('Location: /buyer/orders');
		exit;
	}
}
?>