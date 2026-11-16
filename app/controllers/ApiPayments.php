<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ApiPayments extends Controller
{
    private const XENDIT_API = 'https://api.xendit.co';

    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        $this->call->database();
        $this->call->helper('api');
        $this->call->model('Order', 'order');
        $this->call->model('Payment', 'payment');
    }

    /**
     * Binabasa ang value mula sa .env (getenv o $_ENV).
     */
    private function env($key)
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            $value = $_ENV[$key] ?? ($_SERVER[$key] ?? '');
        }
        return trim((string) $value);
    }

    /**
     * Tumatawag sa Xendit API gamit ang secret key bilang username ng Basic Auth.
     * Ibinabalik ang [http_status, decoded_json].
     */
    private function xenditPost($path, array $body)
    {
        $ch = curl_init(self::XENDIT_API . $path);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERPWD        => $this->env('XENDIT_SECRET_KEY') . ':',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return [$status, $response === false ? null : json_decode($response, true)];
    }

    /**
     * POST /api/orders/xendit-checkout
     * Body: order_id
     * Gumagawa ng Xendit invoice para sa order at ibinabalik ang invoice_url para ma-redirect ang buyer.
     */
    public function xendit_checkout()
    {
        $this->api->require_method('POST');
        $buyerId = api_require_role($this->api, 'buyer', 'Buyer account lang ang puwedeng mag-order.');
        $body = $this->api->body();

        $orderId = (int) ($body['order_id'] ?? 0);
        if ($orderId <= 0) {
            $this->api->respond_error('Kailangan ang order_id.', 422);
        }

        $order = $this->order->_find($orderId);
        if (!$order) {
            $this->api->respond_error('Hindi mahanap ang order.', 404);
        }
        $order = (array) $order;

        if ((int) $order['buyer_id'] !== $buyerId) {
            $this->api->respond_error('Hindi mahanap ang order.', 404);
        }
        if ($order['status'] !== 'pending') {
            $this->api->respond_error('Hindi na puwedeng bayaran ang order na ito.', 409);
        }

        foreach ($this->payment->_find_all_by('order_id', $orderId) ?: [] as $p) {
            if (((array) $p)['payment_status'] === 'paid') {
                $this->api->respond_error('Bayad na ang order na ito.', 409);
            }
        }

        if ($this->env('XENDIT_SECRET_KEY') === '') {
            $this->api->respond_error('Hindi pa naka-set ang Xendit key sa server.', 500);
        }

        $frontend = rtrim($this->env('FRONTEND_URL') ?: 'http://localhost:5173', '/');

        [$status, $data] = $this->xenditPost('/v2/invoices', [
            'external_id'          => 'FC-' . $orderId . '-' . time(),
            'amount'               => round((float) $order['total_amount'], 2),
            'currency'             => 'PHP',
            'description'          => 'FarmConnect order #' . $orderId,
            'invoice_duration'     => 86400,
            'success_redirect_url' => $frontend . '/buyer/dashboard',
            'failure_redirect_url' => $frontend . '/buyer/dashboard',
        ]);

        if (!in_array($status, [200, 201], true) || empty($data['invoice_url']) || empty($data['id'])) {
            $this->api->respond_error('Hindi nagawa ang bayad sa Xendit. Subukan ulit.', 502);
        }

        $this->order->_update($orderId, ['xendit_invoice_id' => $data['id']]);

        $this->api->respond([
            'order_id'    => $orderId,
            'invoice_url' => $data['invoice_url'],
        ]);
    }

    /**
     * POST /api/payments/xendit/webhook
     * Tinatawag ng Xendit kapag bayad na ang invoice.
     * Sinusuri muna ang x-callback-token header bago paniwalaan ang laman.
     */
    public function xendit_webhook()
    {
        $this->api->require_method('POST');

        $expected = $this->env('XENDIT_CALLBACK_TOKEN');
        $received = $_SERVER['HTTP_X_CALLBACK_TOKEN'] ?? '';
        if ($expected === '' || !hash_equals($expected, $received)) {
            $this->api->respond_error('Hindi valid ang callback token.', 401);
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $this->api->respond_error('Mali ang payload.', 400);
        }

        // Ignore ang ibang status; PAID lang ang kailangan natin
        if (($payload['status'] ?? '') !== 'PAID') {
            $this->api->respond(['ignored' => true]);
        }

        $externalId = (string) ($payload['external_id'] ?? '');
        if (!preg_match('/^FC-(\d+)-\d+$/', $externalId, $m)) {
            $this->api->respond_error('Hindi kilalang external_id.', 400);
        }
        $orderId   = (int) $m[1];
        $invoiceId = (string) ($payload['id'] ?? '');

        $order = $this->order->_find($orderId);
        if (!$order) {
            $this->api->respond_error('Hindi mahanap ang order.', 404);
        }
        $order = (array) $order;

        // Kung naka-save na ang bayad na ito, huwag ulitin (kasi nagre-retry ang Xendit)
        foreach ($this->payment->_find_all_by('order_id', $orderId) ?: [] as $p) {
            if (((array) $p)['transaction_reference'] === $invoiceId) {
                $this->api->respond(['message' => 'Naka-save na.']);
            }
        }

        $this->payment->_insert([
            'order_id'              => $orderId,
            'amount'                => (float) ($payload['amount'] ?? $order['total_amount']),
            'payment_method'        => 'xendit',
            'payment_status'        => 'paid',
            'transaction_reference' => $invoiceId,
            'paid_at'               => date('Y-m-d H:i:s'),
        ]);

        if ($order['status'] === 'pending') {
            $this->order->_update($orderId, ['status' => 'confirmed']);
        }

        $this->api->respond(['message' => 'Na-confirm ang bayad.', 'order_id' => $orderId]);
    }
}