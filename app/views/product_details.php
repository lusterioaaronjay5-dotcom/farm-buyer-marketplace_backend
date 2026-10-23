<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($product['product_name']) ?> | Farm Buyer Marketplace</title>
</head>
<body>
	<p>
		<a href="/marketplace">Back to Marketplace</a> |
		<a href="/dashboard">Dashboard</a> |
		<a href="/logout">Logout</a>
	</p>

	<h1><?= htmlspecialchars($product['product_name']) ?></h1>

	<table border="1" cellpadding="8">
		<tr><th>Category</th><td><?= htmlspecialchars($product['category']) ?></td></tr>
		<tr><th>Price</th><td>₱<?= number_format((float) $product['price'], 2) ?> / <?= htmlspecialchars($product['unit']) ?></td></tr>
		<tr><th>Quantity available</th><td><?= (int) $product['quantity_available'] ?></td></tr>
		<tr><th>Availability</th><td><?= htmlspecialchars(ucfirst($product['availability'])) ?></td></tr>
		<tr><th>Farmer</th><td><?= htmlspecialchars($farmer['full_name'] ?? 'Unknown') ?></td></tr>
		<tr><th>Location</th><td><?= htmlspecialchars($product['location'] ?? '') ?></td></tr>
	</table>

	<h3>Description</h3>
	<p><?= nl2br(htmlspecialchars($product['description'] ?? '')) ?></p>

	<?php if ($role === 'buyer' && $product['availability'] === 'available' && (int) $product['quantity_available'] > 0): ?>
	<form method="POST" action="/buyer/cart/add">
		<input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
		<label>Quantity:</label>
		<input type="number" name="quantity" min="1" max="<?= (int) $product['quantity_available'] ?>" value="1" style="width:70px;">
		<button type="submit">Add to Cart</button>
	</form>
	<?php endif; ?>
</body>
</html>