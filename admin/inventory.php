<?php
// Include configuration and start session
require '../includes/config.php';

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

// Handle form submission for stock updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

    // Validate CSRF token
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        header("Location: inventory.php?error=CSRF token validation failed");
        exit;
    }

    // Validate inputs
    if (!$product_id || $stock === false || $stock < 0) {
        header("Location: inventory.php?error=Invalid product ID or stock value");
        exit;
    }

    // Update stock in database
    try {
        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
        $stmt->execute([$stock, $product_id]);
        header("Location: inventory.php?success=Stock updated successfully");
        exit;
    } catch (PDOException $e) {
        header("Location: inventory.php?error=Failed to update stock: " . $e->getMessage());
        exit;
    }
}

// Fetch all products with category name
$stmt = $pdo->query("SELECT p.id, p.name, p.stock, c.name AS category FROM products p LEFT JOIN categories c ON p.category_id = c.id ORDER BY p.name");
$products = $stmt->fetchAll();

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Manage Inventory</h2>
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($_GET['success']); ?></div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($_GET['error']); ?></div>
        <?php endif; ?>
        <div class="table-responsive admin-card">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Update Stock</th>
                    </tr>
                </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo $product['id']; ?></td>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo htmlspecialchars($product['category'] ?: 'N/A'); ?></td>
                        <td><?php echo $product['stock']; ?></td>
                        <td>
                            <form method="POST" action="" class="d-flex">
                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="number" name="stock" class="form-control w-50 d-inline-block" value="<?php echo $product['stock']; ?>" min="0" required>
                                <button type="submit" class="btn btn-sm btn-primary ms-2">Update</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="products.php" class="btn btn-secondary">Back to Products</a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>