<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Dashboard extends Controller {
	public function index() {
		$this->call->library('session');

		$user_id = $this->session->userdata('user_id');
		if (!$user_id) {
			header('Location: /login');
			exit;
		}

		$this->call->view('dashboard', [
			'role' => $this->session->userdata('role'),
			'name' => $this->session->userdata('full_name'),
		]);
	}
}
?>