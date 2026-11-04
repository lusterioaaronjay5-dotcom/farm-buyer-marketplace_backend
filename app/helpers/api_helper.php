<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Shared helper para sa lahat ng API controller.
 * I-load sa controller gamit ang: $this->call->helper('api');
 */

if (!function_exists('api_to_array')) {
    /**
     * Ginagawang array ang anumang hugis ng decoded token (array, object, string, o nakabalot sa "data").
     */
    function api_to_array($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (is_array($value) && isset($value['data']) && (is_array($value['data']) || is_object($value['data']))) {
            $value = (array) $value['data'];
        }

        return is_array($value) ? $value : [];
    }
}

if (!function_exists('api_decode_token')) {
    /**
     * Sinisiguro na may valid na Bearer token at ibinabalik ang payload nito.
     * Hihinto ang request kung walang token o hindi valid.
     */
    function api_decode_token($api)
    {
        $token = $api->get_bearer_token();
        if (!$token) {
            $api->respond_error('Missing token.', 401);
        }

        try {
            $decoded = $api->validate_jwt($token);
        } catch (\Throwable $e) {
            $api->respond_error('Token error: ' . $e->getMessage(), 401);
        }

        $payload = api_to_array($decoded);
        if (empty($payload['user_id'])) {
            $api->respond_error('Invalid or expired token.', 401);
        }

        return $payload;
    }
}

if (!function_exists('api_require_user')) {
    /**
     * Kahit anong naka-login na user. Ibinabalik ang user_id.
     */
    function api_require_user($api)
    {
        $payload = api_decode_token($api);

        return (int) $payload['user_id'];
    }
}

if (!function_exists('api_require_role')) {
    /**
     * Naka-login na user na may tamang role lang. Ibinabalik ang user_id.
     */
    function api_require_role($api, $role, $message = null)
    {
        $payload = api_decode_token($api);

        if (($payload['role'] ?? '') !== $role) {
            $api->respond_error($message ?? 'Hindi puwede ang role na ito.', 403);
        }

        return (int) $payload['user_id'];
    }
}

if (!function_exists('api_fetch_in')) {
    /**
     * Kumukuha ng rows sa isang query gamit ang IN (...). Walang query kung walang laman.
     */
    function api_fetch_in($db, $table, $column, array $values)
    {
        $values = array_values(array_unique($values));
        if (empty($values)) {
            return [];
        }

        return $db->table($table)->in($column, $values)->get_all() ?: [];
    }
}

if (!function_exists('api_key_by')) {
    /**
     * Ginagawang map ang listahan, keyed by isang column (isa lang bawat key).
     */
    function api_key_by(array $rows, $column)
    {
        $map = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            if (!isset($map[$r[$column]])) {
                $map[$r[$column]] = $r;
            }
        }

        return $map;
    }
}

if (!function_exists('api_group_by')) {
    /**
     * Ginagawang listahan ang rows, grouped sa isang column (maraming row bawat key).
     */
    function api_group_by(array $rows, $column)
    {
        $grouped = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            $grouped[$r[$column]][] = $r;
        }

        return $grouped;
    }
}