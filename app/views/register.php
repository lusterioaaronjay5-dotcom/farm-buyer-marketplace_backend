<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Register | Farm Buyer Marketplace</title>
</head>
<body>
	<h1>Create an Account</h1>

	<?php if (!empty($error)): ?>
		<p style="color:red;"><?= htmlspecialchars($error) ?></p>
	<?php endif; ?>

	<form method="POST" action="/register">
		<label>Full Name</label><br>
		<input type="text" name="full_name" value="<?= htmlspecialchars($old['full_name'] ?? '') ?>" required><br><br>

		<label>Email Address</label><br>
		<input type="email" name="email" value="<?= htmlspecialchars($old['email'] ?? '') ?>" required><br><br>

		<label>Account Type</label><br>
		<select name="role" required>
			<option value="">Select account type</option>
			<option value="farmer" <?= (($old['role'] ?? '') === 'farmer') ? 'selected' : '' ?>>Farmer</option>
			<option value="buyer" <?= (($old['role'] ?? '') === 'buyer') ? 'selected' : '' ?>>Buyer</option>
		</select><br><br>

		<label>Password</label><br>
		<input type="password" name="password" minlength="8" required><br><br>

		<label>Confirm Password</label><br>
		<input type="password" name="confirm_password" minlength="8" required><br><br>

		<button type="submit">Create Account</button>
	</form>

	<p>Already registered? <a href="/login">Log in here</a></p>
</body>
</html>