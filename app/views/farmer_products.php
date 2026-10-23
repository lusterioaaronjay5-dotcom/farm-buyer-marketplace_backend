<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Products | Farm Buyer Marketplace</title>
</head>
<body>
	<p><a href="/dashboard">Dashboard</a> | <a href="/logout">Logout</a></p>
	<h1>My Products</h1>
	<p><a href="/farmer/products/add"><button>+ Add Product</button></a></p>

	<?php if (empty($products)): ?>
		<p>No products yet.</p>
	<?php else: ?>
	<table border="1" cellpadding="6">
		<tr>
			<th>Product</th>
			<th>Category</th>
			<th>Price</th>
			<th>Quantity</th>
			<th>Unit</th>
			<th>Availability</th>
			<th>Actions</th>
		</tr>
		<?php foreach ($products as $p): ?>
		<tr>
			<td><?= htmlspecialchars($p['product_name']) ?></td>
			<td><?= htmlspecialchars($p['category']) ?></td>
			<td>₱<?= htmlspecialchars($p['price']) ?></td>
			<td><?= htmlspecialchars($p['quantity_available']) ?></td>
			<td><?= htmlspecialchars($p['unit']) ?></td>
			<td><?= htmlspecialchars($p['availability']) ?></td>
			<td>
				<a href="/farmer/products/edit?id=<?= (int) $p['product_id'] ?>">Edit</a>
				<form method="POST" action="/farmer/products/delete" style="display:inline;" onsubmit="return confirm('Delete this product?');">
					<input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
					<button type="submit">Delete</button>
				</form>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	<?php endif; ?>
</body>
</html>