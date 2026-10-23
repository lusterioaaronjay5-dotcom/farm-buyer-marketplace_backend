<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/**
 * Model: User
 * 
 * Automatically generated via CLI.
 */
class User extends Model {
protected $table = 'users';
protected $primary_key = 'user_id';
protected $fillable = ['full_name', 'email', 'password_hash', 'role', 'phone', 'address'];
protected $guarded = ['user_id'];

    public function __construct()
    {
        parent::__construct();
    }
}