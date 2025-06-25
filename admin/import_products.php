<?php
require '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

function fetchSupplierProducts($supplier) {
    // Mock API call (replace with actual supplier API, e.g., AliExpress)
    $ch = curl_init($supplier['api_url'] . '/products?key=' . $supplier['api_key']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Supplier API error: HTTP $http_code for {$supplier['api_url']}");
        return [];
    }

    // Mock response for testing
    return json_decode('[
        {"id":"123","name":"Sample Product","description":"A sample product","price":10.00,"stock":100,"image":"sample.jpg"},
        {"id":"124","name":"Another Product","description":"Another sample","price":15.00,"stock":50,"image":"another.jpg"}
    ]', true);
}

$error = $success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = filter_input(INPUT_POST, 'supplier_id', FILTER_VALIDATE_INT);
    $profit_margin = filter_input(INPUT_POST, 'profit_margin', FILTER_VALIDATE_FLOAT);
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed.";
    } elseif (!$supplier_id || $profit_margin < 0) {
        $error = "Invalid supplier or profit margin.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
            $stmt->execute([$supplier_id]);
            $supplier = $stmt->fetch();

            if (!$supplier) {
                $error = "Supplier not found.";
            } else {
                $products = fetchSupplierProducts($supplier);
                if (empty($products)) {
                    $error = "No products available from supplier.";
                } else {
                    $pdo->beginTransaction();
                    foreach ($products as $product) {
                        // Check if product exists
                        $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
                        $stmt->execute([$product['name']]);
                        if (!$stmt->fetch()) {
                            // Insert product
                            $price = $product['price'] * (1 + $profit_margin / 100);
                            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock, image, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                            $stmt->execute([$product['name'], $product['description'], $price, $product['stock'], $product['image']]);
                            $product_id = $pdo->lastInsertId();

                            // Link to supplier
                            $stmt = $pdo->prepare("INSERT INTO supplier_products (supplier_id, supplier_product_id, product_id, stock, supplier_price) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$supplier_id, $product['id'], $product_id, $product['stock'], $product['price']]);
                        }
                    }
                    $pdo->commit();
                    $success = "Products imported successfully.";
                    // Invalidate cache
                    if (class_exists('Redis')) {
                        $redis = new Redis();
                        if ($redis->connect('localhost', 6379)) {
                            $redis->del("dashboard_metrics");
                            $redis->del("supplier_products_{$supplier_id}");
                        }
                    } else {
                        error_log("Redis extension not installed. Cache not invalidated.");
                    }
                }
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Product import error: " . $e->getMessage());
            $error = "Failed to import products.";
        }
    }
}

$stmt = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC");
$suppliers = $stmt->fetchAll();

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Products - E-Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5 mb-5 admin-container">
        <h2 class="mb-4 admin-title" style="color: var(--temu-primary);">Import Products</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="mb-3">
                <label for="supplier_id" class="form-label">Select Supplier</label>
                <select class="form-select" id="supplier_id" name="supplier_id" required>
                    <option value="">Choose a supplier</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Please select a supplier.</div>
            </div>
            <div class="mb-3">
                <label for="profit_margin" class="form-label">Profit Margin (%)</label>
                <input type="number" class="form-control" id="profit_margin" name="profit_margin" min="0" step="0.01" value="20" required>
                <div class="invalid-feedback">Please enter a valid profit margin.</div>
            </div>
            <button type="submit" class="btn btn-primary">Import Products</button>
            <a href="dashboard.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/scripts.js"></script>
</body>
</html>