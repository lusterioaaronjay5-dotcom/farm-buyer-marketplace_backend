<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Dashboard | Farm Buyer Marketplace</title>
</head>
<body>
	<h1>Welcome, <?= htmlspecialchars($name) ?></h1>
	<p>Role: <strong><?= htmlspecialchars($role) ?></strong></p>
	<p><a href="/logout">Logout</a></p>

	<?php if ($role === 'farmer'): ?>
		<h2>Farmer Dashboard</h2>
		<ul>
			<li><a href="/farmer/products">My Products</a></li>
			<li><a href="/farmer/orders">Received Orders</a></li>
		</ul>

	<?php elseif ($role === 'buyer'): ?>
		<h2>Buyer Dashboard</h2>
		<ul>
			<li><a href="/marketplace">Browse Products</a></li>
			<li><a href="/buyer/cart">Shopping Cart</a></li>
			<li><a href="/buyer/orders">My Orders</a></li>
		</ul>

	<?php elseif ($role === 'admin'): ?>
		<h2>Admin Dashboard</h2>
		<ul>
			<li><a href="/admin/users">Manage Users</a></li>
			<li><a href="/admin/products">Product Listings</a></li>
			<li><a href="/admin/orders">Manage Orders</a></li>
		</ul>
	<?php endif; ?>
</body>
</html>