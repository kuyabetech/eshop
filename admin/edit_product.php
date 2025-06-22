<?php
// Include configuration and start session
require '../includes/config.php';

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

// Get product ID from query string
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id) {
    header("Location: products.php?error=Invalid product ID");
    exit;
}

// Fetch product details
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");

$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: products.php?error=Product not found");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize inputs
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $category = filter_input(INPUT_POST, 'category', FILTER_SANITIZE_STRING);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $image = $product['image']; // Keep existing image by default

    // Validate required fields
    if (!$name || $price === false || $stock === false) {
        $error = "Please fill in all required fields with valid data.";
    } else {
        // Handle image upload if provided
        if (!empty($_FILES['image']['name'])) {
            $image = $_FILES['image']['name'];
            $target = "../assets/images/" . basename($image);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target)) {
                // Delete old image if it exists and is different
                if ($product['image'] && file_exists("../assets/images/" . $product['image']) && $product['image'] !== $image) {
                    unlink("../assets/images/" . $product['image']);
                }
            } else {
                $error = "Failed to upload image.";
            }
        }

        // Update product in database
        if (!isset($error)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id, stock, image) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->execute([$name, $description, $price, $category_id ?: null, $stock, $image]);
                header("Location: products.php?success=Product updated successfully");
                exit;
            } catch (PDOException $e) {
                $error = "Failed to update product: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Edit Product - <?php echo htmlspecialchars($product['name']); ?></h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="name" class="form-label">Name</label>
                <input type="text" name="name" id="name" class="form-control" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea name="description" id="description" class="form-control"><?php echo htmlspecialchars($product['description']); ?></textarea>
            </div>
            <div class="mb-3">
                <label for="price" class="form-label">Price</label>
                <input type="number" name="price" id="price" class="form-control" step="0.01" value="<?php echo number_format($product['price'], 2); ?>" required>
            </div>
<div class="mb-3">
    <label for="category_id" class="form-label">Category</label>
    <select name="category_id" id="category_id" class="form-control">
        <option value="">Uncategorized</option>
        <?php
        $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
        foreach ($stmt->fetchAll() as $category) {
            echo "<option value='{$category['id']}'" . ($product['category_id'] == $category['id'] ? ' selected' : '') . ">" . htmlspecialchars($category['name']) . "</option>";
        }
        ?>
    </select>
</div>
            <div class="mb-3">
                <label for="stock" class="form-label">Stock</label>
                <input type="number" name="stock" id="stock" class="form-control" value="<?php echo $product['stock']; ?>" required>
            </div>
            <div class="mb-3">
                <label for="image" class="form-label">Image (Leave blank to keep current image)</label>
                <input type="file" name="image" id="image" class="form-control">
                <?php if ($product['image']): ?>
                    <img src="../assets/images/<?php echo htmlspecialchars($product['image']); ?>" alt="Current Image" class="img-thumbnail mt-2" style="max-width: 100px;">
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary">Update Product</button>
            <a href="products.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>