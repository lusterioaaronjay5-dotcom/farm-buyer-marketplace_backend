<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Model: Product
 * 
 * Automatically generated via CLI.
 */
class Product extends Model {
protected $table = 'products';
protected $primary_key = 'product_id';
protected $fillable = ['farmer_id', 'product_name', 'description', 'category', 'price', 'quantity_available', 'unit', 'availability', 'location', 'image_path'];
protected $guarded = ['product_id'];

    public function __construct()
    {
        parent::__construct();
    }
}