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

    /**
     * I-verify ang Google ID token sa server ng Google.
     * Nangangailangan ng gumaganang CA bundle sa PHP cURL.
     * Walang curl_close() dito: deprecated na sa PHP 8.5 at sumisira ng JSON response.
     */
    private function verifyGoogleIdToken($idToken)
    {
        $url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($raw === false) {
            error_log('Google tokeninfo cURL error: ' . curl_error($ch));
        }

        if ($raw === false || $status !== 200) {
            return null;
        }

        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    /**
     * POST /api/auth/google
     * Body: credential (Google ID token), role (buyer|farmer, kapag bago ang Gmail)
     *
     * - Kung may account na ang email: login agad.
     * - Kung bago ang email at walang role: ibabalik ang needs_role para pumili ang user.
     * - Kung bago ang email at may role: gagawa ng account at mag-lo-login.
     */
    public function google()
    {
        $this->api->require_method('POST');
        $body = $this->api->body();

        $credential = trim($body['credential'] ?? '');
        $role       = trim($body['role'] ?? '');

        if ($credential === '') {
            $this->api->respond_error('Walang Google credential.', 422);
        }

        $clientId = getenv('GOOGLE_CLIENT_ID') ?: '';
        if ($clientId === '') {
            $this->api->respond_error('Hindi pa naka-configure ang Google sign-in.', 500);
        }

        $info = $this->verifyGoogleIdToken($credential);
        if (
            !$info
            || ($info['aud'] ?? '') !== $clientId
            || !in_array($info['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true)
            || (int) ($info['exp'] ?? 0) <= time()
            || !filter_var($info['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || empty($info['email'])
        ) {
            $this->api->respond_error('Hindi valid ang Google sign-in. Subukang muli.', 401);
        }

        $email    = strtolower(trim($info['email']));
        $fullName = trim($info['name'] ?? '') ?: $email;

        $user = $this->user->_find_by('email', $email);

        if ($user) {
            $user = (array) $user;
        } else {
            // Bagong Gmail: kailangan pang pumili ng role bago gumawa ng account
            if (!in_array($role, ['farmer', 'buyer'], true)) {
                $this->api->respond([
                    'needs_role' => true,
                    'name'       => $fullName,
                ]);
                return;
            }

            // Random na password hash dahil hindi ito gagamitin sa password login
            $id = $this->user->_insert([
                'full_name'     => $fullName,
                'email'         => $email,
                'password_hash' => password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT),
                'role'          => $role,
                'phone'         => null,
                'address'       => null,
            ]);
            $user = (array) $this->user->_find($id);
        }

        $this->api->respond([
            'token' => $this->issueToken($user),
            'user'  => $this->publicUser($user),
        ]);
    }
}