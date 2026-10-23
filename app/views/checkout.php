<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Checkout | Farm Buyer Marketplace</title>
</head>
<body>
	<p>
		<a href="/dashboard">Dashboard</a> |
		<a href="/marketplace">Marketplace</a> |
		<a href="/buyer/cart">My Cart</a> |
		<a href="/logout">Logout</a>
	</p>
	<h1>Checkout</h1>

	<?php if (!empty($order_id)): ?>
		<h2 style="color:green;">Order #<?= (int) $order_id ?> placed!</h2>
		<p>Payment status: paid (PayMongo test mode, simulated)</p>
		<p><a href="/marketplace">Continue shopping</a></p>
	<?php else: ?>

		<?php if (!empty($error)): ?>
			<p style="color:red;"><?= htmlspecialchars($error) ?></p>
		<?php endif; ?>

		<h3>Order Summary</h3>
		<table border="1" cellpadding="6">
			<tr>
				<th>Product</th>
				<th>Price</th>
				<th>Quantity</th>
				<th>Subtotal</th>
			</tr>
			<?php foreach ($items as $item): $p = $item['product']; ?>
			<tr>
				<td><?= htmlspecialchars($p['product_name']) ?></td>
				<td>₱<?= number_format((float) $p['price'], 2) ?></td>
				<td><?= (int) $item['quantity'] ?></td>
				<td>₱<?= number_format($item['subtotal'], 2) ?></td>
			</tr>
			<?php endforeach; ?>
		</table>
		<h3>Total: ₱<?= number_format($total, 2) ?></h3>

		<form method="POST" action="/buyer/checkout">
			<label>Delivery Address</label><br>
			<textarea name="delivery_address" rows="3" cols="40" required><?= htmlspecialchars($old['delivery_address'] ?? '') ?></textarea><br><br>

			<label>Payment Method (PayMongo test mode)</label><br>
			<select name="payment_method" required>
				<option value="">Select</option>
				<option value="gcash" <?= (($old['payment_method'] ?? '') === 'gcash') ? 'selected' : '' ?>>GCash</option>
				<option value="card" <?= (($old['payment_method'] ?? '') === 'card') ? 'selected' : '' ?>>Card</option>
			</select><br><br>

			<button type="submit">Place Order</button>
		</form>
	<?php endif; ?>
</body>
</html>