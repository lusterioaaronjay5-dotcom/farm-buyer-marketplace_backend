<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Model: OrderItem
 * 
 * Automatically generated via CLI.
 */
class OrderItem extends Model {
protected $table = 'order_items';
protected $primary_key = 'order_item_id';
protected $fillable = ['order_id', 'product_id', 'quantity', 'unit_price', 'subtotal'];
protected $guarded = ['order_item_id'];

    public function __construct()
    {
        parent::__construct();
    }
}