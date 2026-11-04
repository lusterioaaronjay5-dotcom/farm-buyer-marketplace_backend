<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiMessages extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
    }

    /**
     * POST /api/messages
     * Body: receiver_id, body, product_id (optional)
     */
    public function send()
    {
        $this->api->require_method('POST');
        $me = api_require_user($this->api);
        $body = $this->api->body();

        $receiverId = (int) ($body['receiver_id'] ?? 0);
        $text = trim((string) ($body['body'] ?? ''));
        $productId = (isset($body['product_id']) && $body['product_id'] !== '') ? (int) $body['product_id'] : null;

        if ($receiverId <= 0 || $receiverId === $me) {
            $this->api->respond_error('Mali ang tatanggap.', 422);
        }
        if ($text === '') {
            $this->api->respond_error('Walang laman ang mensahe.', 422);
        }
        if (mb_strlen($text) > 1000) {
            $this->api->respond_error('Masyadong mahaba ang mensahe (max 1000 characters).', 422);
        }

        $receiver = $this->db->table('users')->where('user_id', $receiverId)->get_all() ?: [];
        if (empty($receiver)) {
            $this->api->respond_error('Hindi mahanap ang tatanggap.', 404);
        }

        $messageId = $this->db->table('messages')->insert([
            'sender_id'   => $me,
            'receiver_id' => $receiverId,
            'product_id'  => $productId,
            'body'        => $text,
            'created_at'  => date('Y-m-d H:i:s'),
            'read_at'     => null,
        ]);

        $this->api->respond([
            'message'    => 'Naipadala ang mensahe.',
            'message_id' => (int) $messageId,
        ]);
    }

    /**
     * GET /api/messages/thread?with=USER_ID
     * Ibinabalik ang lahat ng mensahe sa pagitan ng dalawang user, at minamarkahan bilang nabasa ang natanggap.
     */
    public function thread()
    {
        $me = api_require_user($this->api);
        $with = (int) ($_GET['with'] ?? 0);

        if ($with <= 0 || $with === $me) {
            $this->api->respond_error('Mali ang kausap.', 422);
        }

        $sent = $this->db->table('messages')
            ->where('sender_id', $me)
            ->where('receiver_id', $with)
            ->get_all() ?: [];
        $received = $this->db->table('messages')
            ->where('sender_id', $with)
            ->where('receiver_id', $me)
            ->get_all() ?: [];

        $messages = array_merge(
            array_map(fn ($m) => (array) $m, $sent),
            array_map(fn ($m) => (array) $m, $received)
        );
        usort($messages, fn ($a, $b) => $a['message_id'] <=> $b['message_id']);

        // Markahan bilang nabasa ang mga hindi pa nababasa na natanggap
        $unreadIds = [];
        foreach ($received as $m) {
            $m = (array) $m;
            if (empty($m['read_at'])) {
                $unreadIds[] = (int) $m['message_id'];
            }
        }
        if (!empty($unreadIds)) {
            $this->db->table('messages')
                ->in('message_id', $unreadIds)
                ->update(['read_at' => date('Y-m-d H:i:s')]);
        }

        $other = $this->db->table('users')
            ->select('user_id, full_name, role')
            ->where('user_id', $with)
            ->get_all() ?: [];

        $this->api->respond([
            'with'     => !empty($other) ? (array) $other[0] : null,
            'messages' => $messages,
        ]);
    }

    /**
     * GET /api/messages/inbox
     * Listahan ng usapan, pinakabago muna. May last message at bilang ng hindi pa nababasa.
     */
    public function inbox()
    {
        $me = api_require_user($this->api);

        $sent = $this->db->table('messages')->where('sender_id', $me)->get_all() ?: [];
        $received = $this->db->table('messages')->where('receiver_id', $me)->get_all() ?: [];

        $all = array_merge(
            array_map(fn ($m) => (array) $m, $sent),
            array_map(fn ($m) => (array) $m, $received)
        );

        $convos = [];
        foreach ($all as $m) {
            $otherId = (int) ($m['sender_id'] == $me ? $m['receiver_id'] : $m['sender_id']);

            if (!isset($convos[$otherId])) {
                $convos[$otherId] = ['user_id' => $otherId, 'last' => $m, 'unread' => 0];
            }
            if ((int) $m['message_id'] > (int) $convos[$otherId]['last']['message_id']) {
                $convos[$otherId]['last'] = $m;
            }
            if ((int) $m['receiver_id'] === $me && empty($m['read_at'])) {
                $convos[$otherId]['unread']++;
            }
        }

        // Kunin ang pangalan ng kausap
        $otherIds = array_keys($convos);
        $users = [];
        if (!empty($otherIds)) {
            $rows = $this->db->table('users')
                ->select('user_id, full_name, role')
                ->in('user_id', $otherIds)
                ->get_all() ?: [];
            foreach ($rows as $row) {
                $u = (array) $row;
                $users[(int) $u['user_id']] = $u;
            }
        }

        $list = [];
        foreach ($convos as $otherId => $c) {
            $list[] = [
                'user_id'   => $otherId,
                'full_name' => $users[$otherId]['full_name'] ?? 'Hindi kilalang user',
                'role'      => $users[$otherId]['role'] ?? null,
                'last_body' => $c['last']['body'],
                'last_at'   => $c['last']['created_at'],
                'unread'    => $c['unread'],
            ];
        }

        usort($list, fn ($a, $b) => strcmp($b['last_at'], $a['last_at']));

        $this->api->respond(['conversations' => $list]);
    }

    /**
     * DELETE /api/messages?id=MESSAGE_ID
     * Ang nagpadala lang ang puwedeng magbura ng sarili niyang mensahe.
     */
    public function destroy()
    {
        $this->api->require_method('DELETE');
        $me = api_require_user($this->api);

        $messageId = (int) ($_GET['id'] ?? 0);
        if ($messageId <= 0) {
            $this->api->respond_error('Kailangan ang id.', 422);
        }

        $rows = $this->db->table('messages')->where('message_id', $messageId)->get_all() ?: [];
        $message = !empty($rows) ? (array) $rows[0] : null;

        if (!$message || (int) $message['sender_id'] !== $me) {
            $this->api->respond_error('Hindi mahanap ang mensahe.', 404);
        }

        $this->db->table('messages')->where('message_id', $messageId)->delete();

        $this->api->respond(['message' => 'Nabura ang mensahe.']);
    }
}