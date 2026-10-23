<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Orders | Farm Buyer Marketplace</title>
</head>
<body>
	<p>
		<a href="/dashboard">Dashboard</a> |
		<a href="/marketplace">Marketplace</a> |
		<a href="/buyer/cart">My Cart</a> |
		<a href="/logout">Logout</a>
	</p>
	<h1>My Orders</h1>

	<?php if (empty($rows)): ?>
		<p>You have no orders yet. <a href="/marketplace">Start shopping</a></p>
	<?php else: ?>
		<?php foreach ($rows as $row): $o = $row['order']; ?>
		<div style="border:1px solid #999; padding:12px; margin-bottom:16px;">
			<h3>Order #<?= (int) $o['order_id'] ?></h3>
			<p>
				Date: <?= htmlspecialchars($o['order_date']) ?><br>
				Order status: <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $o['status']))) ?></strong><br>
				Payment: <strong><?= htmlspecialchars(ucfirst($row['payment']['payment_status'] ?? 'n/a')) ?></strong>
				<?= !empty($row['payment']['payment_method']) ? '(' . htmlspecialchars($row['payment']['payment_method']) . ')' : '' ?><br>
				Delivery: <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $row['delivery']['delivery_status'] ?? 'n/a'))) ?></strong><br>
				Delivery address: <?= htmlspecialchars($o['delivery_address'] ?? '') ?>
			</p>

			<table border="1" cellpadding="6">
				<tr>
					<th>Product</th>
					<th>Price</th>
					<th>Quantity</th>
					<th>Subtotal</th>
				</tr>
				<?php foreach ($row['items'] as $item): ?>
				<tr>
					<td><?= htmlspecialchars($item['name']) ?></td>
					<td>₱<?= number_format((float) $item['unit_price'], 2) ?></td>
					<td><?= (int) $item['quantity'] ?></td>
					<td>₱<?= number_format((float) $item['subtotal'], 2) ?></td>
				</tr>
				<?php endforeach; ?>
			</table>

			<h4>Total: ₱<?= number_format((float) $o['total_amount'], 2) ?></h4>
		</div>
		<?php endforeach; ?>
	<?php endif; ?>
</body>
</html>