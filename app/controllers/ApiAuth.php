<?php

class ApiAuth extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->model('User', 'user');
    }

    /**
     * Kinukuha ang user mula sa Bearer token. Hihinto ang request kung walang valid token.
     */
    private function requireToken()
    {
        $token = $this->api->get_bearer_token();
        if (!$token) {
            $this->api->respond_error('Missing token.', 401);
        }

        $payload = (array) $this->api->validate_jwt($token);
        if (empty($payload['user_id'])) {
            $this->api->respond_error('Invalid or expired token.', 401);
        }

        $user = $this->user->_find((int) $payload['user_id']);
        if (!$user) {
            $this->api->respond_error('User not found.', 401);
        }

        return $this->publicUser((array) $user);
    }

    /**
     * Mga field lang na ligtas ipakita sa frontend. Walang password_hash.
     */
    private function publicUser(array $user)
    {
        return [
            'user_id'   => (int) $user['user_id'],
            'full_name' => $user['full_name'],
            'email'     => $user['email'],
            'role'      => $user['role'],
            'phone'     => $user['phone'] ?? null,
            'address'   => $user['address'] ?? null,
        ];
    }

    /**
     * POST /api/login
     * Body: { "email": "...", "password": "..." }
     */
    public function login()
    {
        $this->api->require_method('POST');
        $body = $this->api->body();

        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';

        if ($email === '' || $password === '') {
            $this->api->respond_error('Email and password are required.', 422);
        }

        $user = $this->user->_find_by('email', $email);
        if (!$user) {
            $this->api->respond_error('Mali ang email o password.', 401);
        }

        $user = (array) $user;
        if (!password_verify($password, $user['password_hash'])) {
            $this->api->respond_error('Mali ang email o password.', 401);
        }

        $token = $this->api->encode_jwt(
            ['user_id' => (int) $user['user_id'], 'role' => $user['role']],
            8 * 60 * 60
        );

        $this->api->respond([
            'token' => $token,
            'user'  => $this->publicUser($user),
        ]);
    }

    /**
     * GET /api/me
     * Header: Authorization: Bearer <token>
     */
    public function me()
    {
        $this->api->require_method('GET');
        $user = $this->requireToken();

        $this->api->respond(['user' => $user]);
    }
}