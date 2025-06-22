<?php
// Include configuration and start session
require '../includes/config.php';

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

// Initialize variables for feedback
$success = [];
$errors = [];

// Generate CSRF token only if not already set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

    // Validate CSRF token
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $errors[] = "CSRF token validation failed.";
    } else {
        // Check if a file was uploaded
        if (!empty($_FILES['stock_file']['name'])) {
            $file = $_FILES['stock_file'];
            $file_type = mime_content_type($file['tmp_name']);
            $allowed_types = ['text/csv', 'application/vnd.ms-excel'];

            // Validate file type and size (max 5MB)
            if (!in_array($file_type, $allowed_types)) {
                $errors[] = "Invalid file type. Please upload a CSV file.";
            } elseif ($file['size'] > 5 * 1024 * 1024) {
                $errors[] = "File size exceeds 5MB limit.";
            } else {
                // Process CSV file
                $handle = fopen($file['tmp_name'], 'r');
                if ($handle !== false) {
                    // Skip header row
                    fgetcsv($handle);

                    // Begin database transaction
                    $pdo->beginTransaction();

                    try {
                        $stmt = $pdo->prepare("UPDATE products SET stock = ? WHERE id = ?");
                        $line = 1;

                        while (($data = fgetcsv($handle)) !== false) {
                            $line++;
                            // Skip empty lines or lines where all columns are empty/whitespace
                            if (empty($data) || array_filter($data, function($v) { return trim($v) !== ''; }) === []) {
                                continue;
                            }
                            // Validate CSV row (expecting product_id, stock)
                            if (count($data) < 2) {
                                $errors[] = "Invalid data at line $line: insufficient columns.";
                                continue;
                            }
                            $product_id_raw = trim($data[0]);
                            $stock_raw = trim($data[1]);
                            if ($product_id_raw === '' || $stock_raw === '') {
                                $errors[] = "Invalid product ID or stock value at line $line.";
                                continue;
                            }
                            $product_id = filter_var($product_id_raw, FILTER_VALIDATE_INT);
                            $stock = filter_var($stock_raw, FILTER_VALIDATE_INT);
                            if ($product_id === false || $stock === false || $stock < 0) {
                                $errors[] = "Invalid product ID or stock value at line $line.";
                                continue;
                            }

                            // Check if product exists
                            $check_stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
                            $check_stmt->execute([$product_id]);
                            if (!$check_stmt->fetch()) {
                                $errors[] = "Product ID $product_id at line $line does not exist.";
                                continue;
                            }

                            // Update stock
                            $stmt->execute([$stock, $product_id]);
                            $success[] = "Updated stock for product ID $product_id to $stock.";
                        }

                        // Commit transaction
                        $pdo->commit();
                    } catch (Exception $e) {
                        // Rollback on error
                        $pdo->rollBack();
                        $errors[] = "Failed to process updates: " . $e->getMessage();
                    }

                    fclose($handle);
                } else {
                    $errors[] = "Failed to open the uploaded file.";
                }
            }
        } else {
            $errors[] = "Please upload a CSV file.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Stock Update</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Bulk Stock Update</h2>
        <p>Upload a CSV file with columns: <code>product_id, stock</code>. Example format:</p>
        <pre>
product_id,stock
1,50
2,100
        </pre>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <ul>
                    <?php foreach ($success as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $msg): ?>
                        <li><?php echo htmlspecialchars($msg); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <div class="mb-3">
                <label for="stock_file" class="form-label">Upload CSV File</label>
                <input type="file" name="stock_file" id="stock_file" class="form-control" accept=".csv" required>
            </div>
            <button type="submit" class="btn btn-primary">Process Updates</button>
            <a href="inventory.php" class="btn btn-secondary">Back to Inventory</a>
        </form>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>