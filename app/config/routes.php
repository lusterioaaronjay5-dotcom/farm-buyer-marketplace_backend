<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
/**
 * ------------------------------------------------------------------
 * LavaLust - an opensource lightweight PHP MVC Framework
 * ------------------------------------------------------------------
 *
 * MIT License
 *
 * Copyright (c) 2020 Ronald M. Marasigan
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @package LavaLust
 * @author Ronald M. Marasigan <ronald.marasigan@yahoo.com>
 * @since Version 1
 * @link https://github.com/ronmarasigan/LavaLust
 * @license https://opensource.org/licenses/MIT MIT License
 */

/*
| -------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------
| Here is where you can register web routes for your application.
|
|
*/
/** @var object $router **/

$router->get('/', 'Welcome::index');
$router->get('/marketplace', 'Marketplace::index');
$router->get('/login', 'Auth::loginForm');
$router->post('/login', 'Auth::loginSubmit');
$router->get('/register', 'Auth::registerForm');
$router->post('/register', 'Auth::registerSubmit');
$router->get('/logout', 'Auth::logout');
$router->get('/dashboard', 'Dashboard::index');
$router->get('/farmer/products', 'FarmerProducts::index');
$router->get('/farmer/products/add', 'FarmerProducts::addForm');
$router->post('/farmer/products/add', 'FarmerProducts::addSubmit');
$router->get('/farmer/products/edit', 'FarmerProducts::editForm');
$router->post('/farmer/products/edit', 'FarmerProducts::editSubmit');
$router->post('/farmer/products/delete', 'FarmerProducts::delete');
$router->get('/buyer/cart', 'BuyerCart::index');
$router->post('/buyer/cart/add', 'BuyerCart::add');
$router->post('/buyer/cart/update', 'BuyerCart::update');
$router->post('/buyer/cart/remove', 'BuyerCart::remove');
$router->get('/buyer/checkout', 'Checkout::index');
$router->post('/buyer/checkout', 'Checkout::place');
$router->get('/buyer/orders', 'BuyerOrders::index');