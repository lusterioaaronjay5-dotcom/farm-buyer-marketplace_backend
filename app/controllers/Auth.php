<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth extends Controller {

	public function loginForm() {
		$this->call->view('login', ['error' => null]);
	}

	public function loginSubmit() {
		$this->call->database();
		$this->call->library('session');
		$this->call->model('User', 'user');

		$email    = trim((string) $this->io->post('email'));
		$password = (string) $this->io->post('password');

		$user = $this->user->_find_by('email', $email);

		if ($user && password_verify($password, $user['password_hash'])) {
			$this->session->set_userdata('user_id', $user['user_id']);
			$this->session->set_userdata('role', $user['role']);
			$this->session->set_userdata('full_name', $user['full_name']);
			header('Location: /marketplace');
			exit;
		}

		$this->call->view('login', ['error' => 'Invalid email or password.']);
	}

	public function registerForm() {
		$this->call->view('register', ['error' => null, 'old' => []]);
	}

	public function registerSubmit() {
		$this->call->database();
		$this->call->model('User', 'user');

		$full_name = trim((string) $this->io->post('full_name'));
		$email     = trim((string) $this->io->post('email'));
		$password  = (string) $this->io->post('password');
		$confirm   = (string) $this->io->post('confirm_password');
		$role      = (string) $this->io->post('role');

		$old = ['full_name' => $full_name, 'email' => $email, 'role' => $role];

		if ($full_name === '' || $email === '' || $password === '' || $role === '') {
			return $this->call->view('register', ['error' => 'Please fill in all fields.', 'old' => $old]);
		}
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return $this->call->view('register', ['error' => 'Invalid email address.', 'old' => $old]);
		}
		if (strlen($password) < 8) {
			return $this->call->view('register', ['error' => 'Password must be at least 8 characters.', 'old' => $old]);
		}
		if ($password !== $confirm) {
			return $this->call->view('register', ['error' => 'Passwords do not match.', 'old' => $old]);
		}
		if (!in_array($role, ['farmer', 'buyer'], true)) {
			return $this->call->view('register', ['error' => 'Please select an account type.', 'old' => $old]);
		}
		if ($this->user->_find_by('email', $email)) {
			return $this->call->view('register', ['error' => 'Email is already registered.', 'old' => $old]);
		}

		$this->user->_insert([
			'full_name'     => $full_name,
			'email'         => $email,
			'password_hash' => password_hash($password, PASSWORD_DEFAULT),
			'role'          => $role,
		]);

		header('Location: /login');
		exit;
	}

	public function logout() {
		$this->call->library('session');
		$this->session->unset_userdata(['user_id', 'role', 'full_name']);
		header('Location: /login');
		exit;
	}
}
?>