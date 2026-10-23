<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Checkout | Farm Buyer Marketplace</title>
	<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
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
		<p><a href="/buyer/orders">View My Orders</a> | <a href="/marketplace">Continue shopping</a></p>
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

			<label>Pin your delivery location on the map (optional)</label><br>
			<button type="button" id="locateBtn">Use my current location</button>
			<p style="margin:6px 0;">Click the map to set your pin.</p>
			<div id="map" style="height:300px; width:100%; max-width:600px;"></div>
			<input type="hidden" name="latitude" id="latitude" value="<?= htmlspecialchars((string) ($old['latitude'] ?? '')) ?>">
			<input type="hidden" name="longitude" id="longitude" value="<?= htmlspecialchars((string) ($old['longitude'] ?? '')) ?>"><br><br>

			<label>Payment Method (PayMongo test mode)</label><br>
			<select name="payment_method" required>
				<option value="">Select</option>
				<option value="gcash" <?= (($old['payment_method'] ?? '') === 'gcash') ? 'selected' : '' ?>>GCash</option>
				<option value="card" <?= (($old['payment_method'] ?? '') === 'card') ? 'selected' : '' ?>>Card</option>
			</select><br><br>

			<button type="submit">Place Order</button>
		</form>

		<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
		<script>
			const latInput = document.getElementById('latitude');
			const lngInput = document.getElementById('longitude');
			const startLat = parseFloat(latInput.value);
			const startLng = parseFloat(lngInput.value);
			const hasStart = !isNaN(startLat) && !isNaN(startLng);

			const map = L.map('map').setView(
				hasStart ? [startLat, startLng] : [12.8797, 121.7740],
				hasStart ? 15 : 6
			);
			L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
				maxZoom: 19,
				attribution: '&copy; OpenStreetMap contributors'
			}).addTo(map);

			let marker = hasStart ? L.marker([startLat, startLng]).addTo(map) : null;

			function setPin(lat, lng) {
				if (marker) {
					marker.setLatLng([lat, lng]);
				} else {
					marker = L.marker([lat, lng]).addTo(map);
				}
				latInput.value = lat.toFixed(7);
				lngInput.value = lng.toFixed(7);
			}

			map.on('click', function (e) {
				setPin(e.latlng.lat, e.latlng.lng);
			});

			document.getElementById('locateBtn').addEventListener('click', function () {
				if (!navigator.geolocation) {
					alert('Geolocation is not supported by your browser.');
					return;
				}
				navigator.geolocation.getCurrentPosition(function (pos) {
					setPin(pos.coords.latitude, pos.coords.longitude);
					map.setView([pos.coords.latitude, pos.coords.longitude], 15);
				}, function () {
					alert('Unable to get your location.');
				});
			});
		</script>
	<?php endif; ?>
</body>
</html>