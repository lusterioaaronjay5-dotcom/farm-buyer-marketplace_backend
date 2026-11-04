<?php

class ApiProfile extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
        $this->call->model('User', 'user');
    }

    private function toArray($user)
    {
        if (is_object($user)) {
            return (array) $user;
        }
        return $user;
    }

    private function publicProfile($user)
    {
        $user = $this->toArray($user);

        return [
            'user_id'         => $user['user_id'],
            'full_name'       => $user['full_name'],
            'email'           => $user['email'],
            'role'            => $user['role'],
            'phone'           => $user['phone'],
            'address'         => $user['address'],
            'profile_picture' => $user['profile_picture'],
        ];
    }

    // GET /api/profile
    public function show()
    {
        $this->api->require_method('GET');
        $userId = api_require_user($this->api);

        $user = $this->user->_find($userId);
        if (!$user) {
            $this->api->respond_error('User not found', 404);
            return;
        }

        $this->api->respond([
            'profile' => $this->publicProfile($user),
        ]);
    }

    // POST /api/profile
    public function update()
    {
        $this->api->require_method('POST');
        $userId = api_require_user($this->api);

        $body = $this->api->body();

        $fullName = trim($body['full_name'] ?? '');
        $phone    = trim($body['phone'] ?? '');
        $address  = trim($body['address'] ?? '');
        $remove   = !empty($body['remove_picture']);
        $picture  = $body['profile_picture'] ?? null;

        if ($fullName === '') {
            $this->api->respond_error('Kailangan ang buong pangalan.', 422);
            return;
        }
        if (mb_strlen($fullName) > 100) {
            $this->api->respond_error('Masyadong mahaba ang pangalan (max 100).', 422);
            return;
        }
        if (mb_strlen($phone) > 20) {
            $this->api->respond_error('Masyadong mahaba ang phone (max 20).', 422);
            return;
        }
        if (mb_strlen($address) > 500) {
            $this->api->respond_error('Masyadong mahaba ang address (max 500).', 422);
            return;
        }

        $data = [
            'full_name' => $fullName,
            'phone'     => $phone === '' ? null : $phone,
            'address'   => $address === '' ? null : $address,
        ];

        if ($remove) {
            $data['profile_picture'] = null;
        } elseif (!empty($picture)) {
            if (!preg_match('#^data:image/(png|jpe?g|webp);base64,#', $picture)) {
                $this->api->respond_error('Hindi suportadong format ng larawan.', 422);
                return;
            }
            if (strlen($picture) > 1500000) {
                $this->api->respond_error('Masyadong malaki ang larawan (max 1MB).', 422);
                return;
            }
            $data['profile_picture'] = $picture;
        }

        $this->user->_update($userId, $data);

        $user = $this->user->_find($userId);

        $this->api->respond([
            'message' => 'Na-save ang profile.',
            'profile' => $this->publicProfile($user),
        ]);
    }
}