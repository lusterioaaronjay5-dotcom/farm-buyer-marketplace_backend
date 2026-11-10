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

// Auth
$router->post('/api/login', 'ApiAuth::login');
$router->post('/api/auth/google', 'ApiAuth::google');
$router->get('/api/me', 'ApiAuth::me');
$router->post('/api/register', 'ApiAuth::register');

// Products (buyer)
$router->get('/api/products', 'ApiProducts::index');
$router->get('/api/products/detail', 'ApiProducts::show');

// Products (farmer)
$router->get('/api/farmer/products', 'ApiFarmerProducts::index');
$router->post('/api/farmer/products', 'ApiFarmerProducts::store');
$router->post('/api/farmer/products/update', 'ApiFarmerProducts::update');
$router->post('/api/farmer/products/delete', 'ApiFarmerProducts::destroy');

// Orders (farmer)
$router->get('/api/farmer/orders', 'ApiFarmerOrders::index');
$router->post('/api/farmer/orders/status', 'ApiFarmerOrders::updateStatus');

// Dashboard (farmer)
$router->get('/api/farmer/dashboard', 'ApiFarmerDashboard::index');

// Orders (buyer)
$router->post('/api/orders', 'ApiOrders::store');
$router->get('/api/orders', 'ApiOrders::index');
$router->post('/api/orders/pay', 'ApiOrders::pay');
$router->post('/api/orders/cancel', 'ApiOrders::cancel');
$router->get('/api/geocode', 'ApiOrders::geocode');

// Profile
$router->get('/api/profile', 'ApiProfile::show');
$router->post('/api/profile', 'ApiProfile::update');

// Farmer profile (buyer view)
$router->get('/api/farmers/detail', 'ApiFarmers::show');

// Messages (buyer at farmer)
$router->get('/api/messages/inbox', 'ApiMessages::inbox');
$router->get('/api/messages/thread', 'ApiMessages::thread');
$router->post('/api/messages', 'ApiMessages::send');
$router->delete('/api/messages', 'ApiMessages::destroy');

$router->get('/api/farmer/sales-summary', 'ApiFarmerSales::summary');