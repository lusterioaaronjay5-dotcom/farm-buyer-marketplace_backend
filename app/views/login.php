<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Login | Farm Buyer Marketplace</title>
</head>
<body>
	<h1>Welcome Back</h1>
	<p>Log in using your registered account.</p>

	<?php if (!empty($error)): ?>
		<p style="color:red;"><?= htmlspecialchars($error) ?></p>
	<?php endif; ?>

	<form method="POST" action="/login">
		<label>Email Address</label><br>
		<input type="email" name="email" required><br><br>

		<label>Password</label><br>
		<input type="password" name="password" required><br><br>

		<button type="submit">Login</button>
	</form>

	<p>New to the marketplace? <a href="/register">Create an account</a></p>
</body>
</html>
