<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Shopping Cart | Farm Buyer Marketplace</title>
</head>
<body>
	<p><a href="/dashboard">Dashboard</a> | <a href="/marketplace">Marketplace</a> | <a href="/logout">Logout</a></p>
	<h1>Shopping Cart</h1>

	<?php if (empty($items)): ?>
		<p>Your cart is empty. <a href="/marketplace">Browse products</a></p>
	<?php else: ?>
	<table border="1" cellpadding="6">
		<tr>
			<th>Product</th>
			<th>Price</th>
			<th>Quantity</th>
			<th>Subtotal</th>
			<th>Action</th>
		</tr>
		<?php foreach ($items as $item): $p = $item['product']; ?>
		<tr>
			<td><?= htmlspecialchars($p['product_name']) ?></td>
			<td>₱<?= number_format((float) $p['price'], 2) ?></td>
			<td>
				<form method="POST" action="/buyer/cart/update" style="display:inline;">
					<input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
					<input type="number" name="quantity" min="0" max="<?= (int) $p['quantity_available'] ?>" value="<?= $item['quantity'] ?>" style="width:70px;">
					<button type="submit">Update</button>
				</form>
			</td>
			<td>₱<?= number_format($item['subtotal'], 2) ?></td>
			<td>
				<form method="POST" action="/buyer/cart/remove" style="display:inline;">
					<input type="hidden" name="product_id" value="<?= (int) $p['product_id'] ?>">
					<button type="submit">Remove</button>
				</form>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>

	<h3>Total: ₱<?= number_format($total, 2) ?></h3>
	<p><button disabled>Proceed to Checkout (next step)</button></p>
	<?php endif; ?>
</body>
</html>