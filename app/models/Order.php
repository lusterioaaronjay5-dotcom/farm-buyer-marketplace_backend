<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Model: Order
 * 
 * Automatically generated via CLI.
 */
class Order extends Model {
protected $table = 'orders';
protected $primary_key = 'order_id';
protected $fillable = ['buyer_id', 'order_date', 'total_amount', 'status', 'delivery_address', 'notes'];
protected $guarded = ['order_id'];

    public function __construct()
    {
        parent::__construct();
    }
}