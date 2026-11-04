<?php

class ApiAuth extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
        $this->call->model('User', 'user');
    }

    /**
     * Kinukuha ang user mula sa Bearer token. Hihinto ang request kung walang valid token.
     */
    private function requireToken()
    {
        $payload = api_decode_token($this->api);

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

    private function issueToken(array $user)
    {
        return $this->api->encode_jwt(
            [
                'sub'     => (int) $user['user_id'],
                'type'    => 'access',
                'user_id' => (int) $user['user_id'],
                'role'    => $user['role'],
            ],
            8 * 60 * 60
        );
    }

    /**
     * POST /api/login
     * Body: email, password
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

        $this->api->respond([
            'token' => $this->issueToken($user),
            'user'  => $this->publicUser($user),
        ]);
    }

    /**
     * POST /api/register
     * Body: full_name, email, password, role (farmer|buyer), phone, address
     */
    public function register()
    {
        $this->api->require_method('POST');
        $body = $this->api->body();

        $fullName = trim($body['full_name'] ?? '');
        $email    = strtolower(trim($body['email'] ?? ''));
        $password = $body['password'] ?? '';
        $role     = $body['role'] ?? '';
        $phone    = trim($body['phone'] ?? '');
        $address  = trim($body['address'] ?? '');

        $errors = [];
        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email is required.';
        }
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        }
        if (!in_array($role, ['farmer', 'buyer'], true)) {
            $errors[] = 'Role must be farmer or buyer.';
        }

        if (!empty($errors)) {
            $this->api->respond_error($errors[0], 422);
        }

        if ($this->user->_find_by('email', $email)) {
            $this->api->respond_error('Ang email na ito ay registered na.', 409);
        }

        $id = $this->user->_insert([
            'full_name'     => $fullName,
            'email'         => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role'          => $role,
            'phone'         => $phone !== '' ? $phone : null,
            'address'       => $address !== '' ? $address : null,
        ]);

        $user = (array) $this->user->_find($id);

        $this->api->respond([
            'token' => $this->issueToken($user),
            'user'  => $this->publicUser($user),
        ]);
    }

    /**
     * GET /api/me
     */
    public function me()
    {
        $this->api->require_method('GET');
        $user = $this->requireToken();

        $this->api->respond(['user' => $user]);
    }
}