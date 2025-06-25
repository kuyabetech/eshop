<?php
session_start();
require '../includes/config.php';

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = null;
$error = null;

// Handle Add or Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        die("CSRF token validation failed.");
    }

    $action = $_POST['action'] ?? 'add';
    $code = htmlspecialchars(trim($_POST['code'] ?? ''));
    $discount_percentage = floatval($_POST['discount_percentage'] ?? 0);
    $valid_from = $_POST['valid_from'] ?? '';
    $valid_until = $_POST['valid_until'] ?? '';
    $max_uses = intval($_POST['max_uses'] ?? 0);

    try {
        if ($action === 'edit' && isset($_POST['id'])) {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
            if (!$id) {
                $error = "Invalid discount ID.";
            } else {
                $stmt = $pdo->prepare("UPDATE discount_codes SET code = ?, discount_percentage = ?, valid_from = ?, valid_until = ?, max_uses = ? WHERE id = ?");
                $stmt->execute([$code, $discount_percentage, $valid_from, $valid_until, $max_uses, $id]);
                $success = "Discount code '$code' updated successfully.";
            }
        } else {
            $stmt = $pdo->prepare("INSERT INTO discount_codes (code, discount_percentage, valid_from, valid_until, max_uses) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$code, $discount_percentage, $valid_from, $valid_until, $max_uses]);
            $success = "Discount code '$code' added successfully!";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $csrf_token = $_GET['csrf_token'] ?? '';
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $error = "CSRF token validation failed.";
    } elseif (!$id) {
        $error = "Invalid discount ID.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM discount_codes WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Discount code deleted successfully.";
        } catch (PDOException $e) {
            $error = "Failed to delete discount code: " . $e->getMessage();
        }
    }
}

// Fetch discounts
$stmt = $pdo->query("SELECT * FROM discount_codes ORDER BY created_at DESC");
$discounts = $stmt->fetchAll();

// Check for edit
$edit_discount = null;
if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM discount_codes WHERE id = ?");
        $stmt->execute([$id]);
        $edit_discount = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Discount Codes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="../assets/css/styles.css" rel="stylesheet">  
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Manage Discount Codes</h2>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add/Edit Discount Form -->
        <h4><?php echo $edit_discount ? 'Edit Discount Code' : 'Add New Discount Code'; ?></h4>
        <form method="POST" action="" class="admin-card p-4 m-4" novalidate>
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            <input type="hidden" name="action" value="<?php echo $edit_discount ? 'edit' : 'add'; ?>">
            <?php if ($edit_discount): ?>
                <input type="hidden" name="id" value="<?php echo $edit_discount['id']; ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="code" class="form-label">Discount Code</label>
                <input type="text" name="code" id="code" class="form-control" value="<?php echo $edit_discount ? htmlspecialchars($edit_discount['code']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="discount_percentage" class="form-label">Discount Percentage (0-100)</label>
                <input type="number" name="discount_percentage" id="discount_percentage" class="form-control" step="0.01" min="0" max="100" value="<?php echo $edit_discount ? $edit_discount['discount_percentage'] : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="valid_from" class="form-label">Valid From</label>
                <input type="datetime-local" name="valid_from" id="valid_from" class="form-control" value="<?php echo $edit_discount ? date('Y-m-d\TH:i', strtotime($edit_discount['valid_from'])) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="valid_until" class="form-label">Valid Until</label>
                <input type="datetime-local" name="valid_until" id="valid_until" class="form-control" value="<?php echo $edit_discount ? date('Y-m-d\TH:i', strtotime($edit_discount['valid_until'])) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="max_uses" class="form-label">Max Uses (0 for unlimited)</label>
                <input type="number" name="max_uses" id="max_uses" class="form-control" min="0" value="<?php echo $edit_discount ? $edit_discount['max_uses'] : '0'; ?>">
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $edit_discount ? 'Update Discount' : 'Add Discount'; ?></button>
            <?php if ($edit_discount): ?>
                <a href="discounts.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>

        <!-- Discount List -->
        <h4 class="mt-5">Discount Codes</h4>
        <div class="table-responsive m-4 p-4">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Discount (%)</th>
                        <th>Valid From</th>
                        <th>Valid Until</th>
                        <th>Uses/Max</th>
                        <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($discounts as $discount): ?>
                    <tr>
                        <td><?php echo $discount['id']; ?></td>
                        <td><?php echo htmlspecialchars($discount['code']); ?></td>
                        <td><?php echo number_format($discount['discount_percentage'], 2); ?>%</td>
                        <td><?php echo date('F j, Y, H:i', strtotime($discount['valid_from'])); ?></td>
                        <td><?php echo date('F j, Y, H:i', strtotime($discount['valid_until'])); ?></td>
                        <td><?php echo $discount['uses'] . '/' . ($discount['max_uses'] ?: 'Unlimited'); ?></td>
                        <td>
                            <a href="?action=edit&id=<?php echo $discount['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="?action=delete&id=<?php echo $discount['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this discount code?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="products.php" class="btn btn-secondary">Back to Products</a>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script>
    document.querySelector('form[action*="apply_discount"]').addEventListener('submit', function(e) {
        const code = document.querySelector('input[name="discount_code"]').value;
        if (!code.match(/^[A-Z0-9]{4,20}$/)) {
            alert('Discount code must be 4-20 alphanumeric characters.');
            e.preventDefault();
        }
    });
</script>
</body>
</html>