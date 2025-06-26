<?php
require '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($pdo) || !$pdo) {
    die("Database connection not established.");
}

$error = $success = null;
$name = $api_key = $api_url = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $api_key = filter_input(INPUT_POST, 'api_key', FILTER_SANITIZE_STRING);
    $api_url = filter_input(INPUT_POST, 'api_url', FILTER_SANITIZE_URL);
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed.";
    } elseif (!$name || !$api_key || !$api_url) {
        $error = "All fields are required.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO suppliers (name, api_key, api_url) VALUES (?, ?, ?)");
            $stmt->execute([$name, $api_key, $api_url]);
            if ($stmt->rowCount() > 0) {
                $success = "Supplier added successfully.";
                $name = $api_key = $api_url = '';
                // Invalidate cache
                if (class_exists('Redis')) {
                    $redis = new Redis();
                    if ($redis->connect('localhost', 6379)) {
                        $redis->del("dashboard_metrics");
                    }
                }
            } else {
                $error = "Failed to add supplier. No rows affected.";
            }
        } catch (PDOException $e) {
            error_log("Add supplier error: " . $e->getMessage());
            // Uncomment the next line for debugging, then remove/comment it after fixing
            $error = "Failed to add supplier: " . $e->getMessage();
            // $error = "Failed to add supplier.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Supplier - E-Shop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5 mb-5 admin-container">
        <h2 class="mb-4 admin-title" style="color: var(--temu-primary);">Add Supplier</h2>
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
                <label for="name" class="form-label">Supplier Name</label>
                <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($name); ?>">
                <div class="invalid-feedback">Please enter a supplier name.</div>
            </div>
            <div class="mb-3">
                <label for="api_key" class="form-label">API Key</label>
                <input type="text" class="form-control" id="api_key" name="api_key" required value="<?php echo htmlspecialchars($api_key); ?>">
                <div class="invalid-feedback">Please enter an API key.</div>
            </div>
            <div class="mb-3">
                <label for="api_url" class="form-label">API URL</label>
                <input type="url" class="form-control" id="api_url" name="api_url" required value="<?php echo htmlspecialchars($api_url); ?>">
                <div class="invalid-feedback">Please enter a valid API URL.</div>
            </div>
            <button type="submit" class="btn btn-primary">Add Supplier</button>
            <a href="index.php" class="btn btn-outline-secondary ms-2">Cancel</a>
        </form>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/scripts.js"></script>
</body>
</html>