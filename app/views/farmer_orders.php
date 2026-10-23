<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Received Orders | Farm Buyer Marketplace</title>
</head>
<body>
	<p>
		<a href="/dashboard">Dashboard</a> |
		<a href="/farmer/products">My Products</a> |
		<a href="/logout">Logout</a>
	</p>
	<h1>Received Orders</h1>

	<?php if (empty($rows)): ?>
		<p>No orders for your products yet.</p>
	<?php else: ?>
		<?php foreach ($rows as $row): $o = $row['order']; $d = $row['delivery']; ?>
		<div style="border:1px solid #999; padding:12px; margin-bottom:16px;">
			<h3>Order #<?= (int) $o['order_id'] ?></h3>
			<p>
				Date: <?= htmlspecialchars($o['order_date']) ?><br>
				Buyer: <?= htmlspecialchars($row['buyer']['full_name'] ?? 'Unknown') ?><br>
				Delivery address: <?= htmlspecialchars($o['delivery_address'] ?? '') ?><br>
				Your sales for this order: <strong>₱<?= number_format($row['farmer_total'], 2) ?></strong>
			</p>

			<table border="1" cellpadding="6">
				<tr>
					<th>Product</th>
					<th>Price</th>
					<th>Quantity</th>
					<th>Subtotal</th>
				</tr>
				<?php foreach ($row['entries'] as $e): ?>
				<tr>
					<td><?= htmlspecialchars($e['name']) ?></td>
					<td>₱<?= number_format((float) $e['unit_price'], 2) ?></td>
					<td><?= $e['quantity'] ?></td>
					<td>₱<?= number_format($e['subtotal'], 2) ?></td>
				</tr>
				<?php endforeach; ?>
			</table>

			<form method="POST" action="/farmer/orders/status" style="margin-top:10px;">
				<input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
				<label>Order status:</label>
				<select name="status">
					<?php foreach ($statuses as $s): ?>
						<option value="<?= $s ?>" <?= ($o['status'] === $s) ? 'selected' : '' ?>>
							<?= ucwords(str_replace('_', ' ', $s)) ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit">Update Status</button>
			</form>

			<form method="POST" action="/farmer/deliveries/status" style="margin-top:8px;">
				<input type="hidden" name="order_id" value="<?= (int) $o['order_id'] ?>">
				<label>Delivery status:</label>
				<select name="delivery_status">
					<?php $current = $d['delivery_status'] ?? 'pending'; ?>
					<?php foreach ($deliveryStatuses as $ds): ?>
						<option value="<?= $ds ?>" <?= ($current === $ds) ? 'selected' : '' ?>>
							<?= ucwords(str_replace('_', ' ', $ds)) ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit">Update Delivery</button>
			</form>
		</div>
		<?php endforeach; ?>
	<?php endif; ?>
</body>
</html>