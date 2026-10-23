<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Marketplace | Farm Buyer Marketplace</title>
</head>
<body>
	<p>
		<a href="/dashboard">Dashboard</a> |
		<a href="/buyer/cart">My Cart</a> |
		<a href="/logout">Logout</a>
	</p>
	<h1>Available Products</h1>

	<?php if (empty($products)): ?>
		<p>No products yet.</p>
	<?php else: ?>
	<table border="1" cellpadding="6">
		<tr>
			<th>Name</th>
			<th>Category</th>
			<th>Price</th>
			<th>Quantity</th>
			<th>Availability</th>
			<th>Add to Cart</th>
		</tr>
		<?php foreach ($products as $p): ?>
		<tr>
			<td><a href="/product?id=<?= (int) $p['product_id'] ?>"><?= htmlspecialchars($p['product_name']) ?></a></td>
			<td><?= htmlspecialchars($p['category']) ?></td>
			<td>₱<?= htmlspecialchars($p['price']) ?></td>
			<td><?= htmlspecialchars($p['quantity_available']) ?></td>
			<td><?= htmlspecialchars($p['availability']) ?></td>
			<td>
				<?php if ($p['availability'] === 'available' && (int) $p['quantity_available'] > 0): ?>
				<form method="POST" action="/buyer/cart/add" style="display:inline;">
					<input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
					<input type="number" name="quantity" min="1" max="<?= (int) $p['quantity_available'] ?>" value="1" style="width:70px;">
					<button type="submit">Add</button>
				</form>
				<?php else: ?>
					Not available
				<?php endif; ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	<?php endif; ?>
</body>
</html>