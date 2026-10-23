<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Model: Payment
 * 
 * Automatically generated via CLI.
 */
class Payment extends Model {
protected $table = 'payments';
protected $primary_key = 'payment_id';
protected $fillable = ['order_id', 'amount', 'payment_method', 'payment_status', 'transaction_reference', 'paid_at'];
protected $guarded = ['payment_id'];

    public function __construct()
    {
        parent::__construct();
    }
}