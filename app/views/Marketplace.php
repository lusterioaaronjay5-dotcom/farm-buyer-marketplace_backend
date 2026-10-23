<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Marketplace</title>
</head>
<body>
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
		</tr>
		<?php foreach ($products as $p): ?>
		<tr>
			<td><?= htmlspecialchars($p['product_name']) ?></td>
			<td><?= htmlspecialchars($p['category']) ?></td>
			<td>₱<?= htmlspecialchars($p['price']) ?></td>
			<td><?= htmlspecialchars($p['quantity_available']) ?></td>
			<td><?= htmlspecialchars($p['availability']) ?></td>
		</tr>
		<?php endforeach; ?>
	</table>
	<?php endif; ?>
</body>
</html>