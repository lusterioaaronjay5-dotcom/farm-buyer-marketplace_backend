<?php
$isEdit = ($mode === 'edit');
$action = $isEdit ? '/farmer/products/edit' : '/farmer/products/add';
$v = function ($key) use ($product) {
	return htmlspecialchars((string) ($product[$key] ?? ''));
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= $isEdit ? 'Edit' : 'Add' ?> Product | Farm Buyer Marketplace</title>
</head>
<body>
	<p><a href="/farmer/products">Back to My Products</a></p>
	<h1><?= $isEdit ? 'Edit Product' : 'Add Product' ?></h1>

	<?php if (!empty($error)): ?>
		<p style="color:red;"><?= htmlspecialchars($error) ?></p>
	<?php endif; ?>

	<form method="POST" action="<?= $action ?>">
		<?php if ($isEdit): ?>
			<input type="hidden" name="product_id" value="<?= (int) $product['product_id'] ?>">
		<?php endif; ?>

		<label>Product Name</label><br>
		<input type="text" name="product_name" value="<?= $v('product_name') ?>" required><br><br>

		<label>Description</label><br>
		<textarea name="description" rows="3" cols="40"><?= $v('description') ?></textarea><br><br>

		<label>Category</label><br>
		<input type="text" name="category" value="<?= $v('category') ?>" required><br><br>

		<label>Price (₱)</label><br>
		<input type="number" step="0.01" min="0" name="price" value="<?= $v('price') ?>" required><br><br>

		<label>Quantity Available</label><br>
		<input type="number" min="0" name="quantity_available" value="<?= $v('quantity_available') ?>" required><br><br>

		<label>Unit</label><br>
		<input type="text" name="unit" placeholder="kg, bundle, piece" value="<?= $v('unit') ?>" required><br><br>

		<label>Availability</label><br>
		<select name="availability" required>
			<option value="">Select</option>
			<option value="available" <?= ($v('availability') === 'available') ? 'selected' : '' ?>>Available</option>
			<option value="unavailable" <?= ($v('availability') === 'unavailable') ? 'selected' : '' ?>>Unavailable</option>
		</select><br><br>

		<label>Location</label><br>
		<input type="text" name="location" value="<?= $v('location') ?>"><br><br>

		<button type="submit"><?= $isEdit ? 'Save Changes' : 'Add Product' ?></button>
	</form>
</body>
</html>