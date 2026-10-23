<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>My Orders | Farm Buyer Marketplace</title>
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>
	<p>
		<a href="/dashboard">Dashboard</a> |
		<a href="/marketplace">Marketplace</a> |
		<a href="/buyer/cart">My Cart</a> |
		<a href="/logout">Logout</a>
	</p>
	<h1>My Orders</h1>

	<?php
	$mapData = [];
	foreach ($rows as $row) {
		$d = $row['delivery'] ?? null;
		if (!empty($d) && $d['latitude'] !== null && $d['longitude'] !== null) {
			$mapData[] = [
				'id'    => (int) $row['order']['order_id'],
				'lat'   => (float) $d['latitude'],
				'lng'   => (float) $d['longitude'],
				'label' => 'Order #' . (int) $row['order']['order_id'] . ' delivery location',
			];
		}
	}
	?>

	<?php if (empty($rows)): ?>
		<p>You have no orders yet. <a href="/marketplace">Start shopping</a></p>
	<?php else: ?>
		<?php foreach ($rows as $row): $o = $row['order']; $d = $row['delivery'] ?? null; ?>
		<div style="border:1px solid #999; padding:12px; margin-bottom:16px;">
			<h3>Order #<?= (int) $o['order_id'] ?></h3>
			<p>
				Date: <?= htmlspecialchars($o['order_date']) ?><br>
				Order status: <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $o['status']))) ?></strong><br>
				Payment: <strong><?= htmlspecialchars(ucfirst($row['payment']['payment_status'] ?? 'n/a')) ?></strong>
				<?= !empty($row['payment']['payment_method']) ? '(' . htmlspecialchars($row['payment']['payment_method']) . ')' : '' ?><br>
				Delivery: <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $d['delivery_status'] ?? 'n/a'))) ?></strong><br>
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

			<?php if (!empty($d) && $d['latitude'] !== null && $d['longitude'] !== null): ?>
				<div id="map-<?= (int) $o['order_id'] ?>" style="height:250px; width:100%; max-width:600px;"></div>
			<?php endif; ?>
		</div>
		<?php endforeach; ?>
	<?php endif; ?>

	<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
	<script>
		const mapData = <?= json_encode($mapData) ?>;
		mapData.forEach(function (d) {
			const m = L.map('map-' + d.id).setView([d.lat, d.lng], 15);
			L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; OpenStreetMap contributors'
			}).addTo(m);
			L.marker([d.lat, d.lng]).addTo(m).bindPopup(d.label).openPopup();
		});
	</script>
</body>
</html>