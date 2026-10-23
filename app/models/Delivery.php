<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Model: Delivery
 * 
 * Automatically generated via CLI.
 */
class Delivery extends Model {
protected $table = 'deliveries';
protected $primary_key = 'delivery_id';
protected $fillable = ['order_id', 'delivery_status', 'delivery_address', 'latitude', 'longitude'];
protected $guarded = ['delivery_id'];

    public function __construct()
    {
        parent::__construct();
    }
}