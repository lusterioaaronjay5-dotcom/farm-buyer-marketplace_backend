<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>E-Wallet Payment | Farm Buyer Marketplace</title>
</head>
<body>
	<p>
		<a href="/dashboard">Dashboard</a> |
		<a href="/buyer/orders">My Orders</a> |
		<a href="/logout">Logout</a>
	</p>
	<h1>E-Wallet Payment</h1>

	<?php
	$methodLabel = $payment['payment_method'] === 'gcash' ? 'GCash' : 'Card';
	$isPaid = $payment['payment_status'] === 'paid';
	?>

	<?php if ($isPaid): ?>
		<h2 style="color:green;">Payment received!</h2>
		<p>Order #<?= (int) $order['order_id'] ?> is already paid.</p>
		<p><a href="/buyer/orders">Back to My Orders</a></p>
	<?php else: ?>
		<div style="border:1px solid #999; padding:16px; max-width:360px;">
			<p><strong>Order #<?= (int) $order['order_id'] ?></strong></p>
			<p>Amount due: <strong>₱<?= number_format((float) $payment['amount'], 2) ?></strong></p>
			<p>Method: <strong><?= htmlspecialchars($methodLabel) ?> (simulated)</strong></p>

			<div style="width:180px; height:180px; border:2px dashed #666; margin:12px 0; display:flex; align-items:center; justify-content:center; text-align:center;">
				Simulated QR code
			</div>

			<p style="font-size:12px; color:#555;">
				This is a demo payment. No real money is charged.
			</p>

			<form method="POST" action="/buyer/pay/confirm">
				<input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
				<button type="submit">Confirm Payment</button>
			</form>
		</div>
	<?php endif; ?>
</body>
</html>