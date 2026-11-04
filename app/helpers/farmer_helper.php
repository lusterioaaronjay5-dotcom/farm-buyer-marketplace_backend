<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Helper para sa farmer API controllers. Gumagamit ng api_helper.
 * I-load sa controller gamit ang: $this->call->helper('farmer');
 */

if (!function_exists('farmer_require_farmer')) {
    /**
     * Sinisiguro na farmer ang naka-login. Ibinabalik ang user_id.
     */
    function farmer_require_farmer($api)
    {
        return api_require_role($api, 'farmer', 'Farmers lang ang puwedeng gumamit nito.');
    }
}

if (!function_exists('farmer_fetch_in')) {
    function farmer_fetch_in($db, $table, $column, array $values)
    {
        return api_fetch_in($db, $table, $column, $values);
    }
}