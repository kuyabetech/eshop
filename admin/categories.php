<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and start session
require '../includes/config.php';

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

// Initialize variables for feedback
$success = null;
$error = null;

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}(random_bytes(32));

// Handle form submissions (add/edit category)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
$csrf_token = trim(filter_input(INPUT_POST, 'csrf_token', FILTER_DEFAULT));

    // Validate CSRF token
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed.";
    } else {

$name = trim(filter_input(INPUT_POST, 'name', FILTER_DEFAULT));
$description = trim(filter_input(INPUT_POST, 'description', FILTER_DEFAULT));

        // Validate input
        if (!$name) {
            $error = "Category name is required.";
        } else {
            try {
                if ($_POST['action'] === 'add') {
                    // Add new category
                    $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $description]);
                    $success = "Category '$name' added successfully.";
                } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
                    // Edit existing category
                    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                    if (!$id) {
                        $error = "Invalid category ID.";
                    } else {
                        $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                        $stmt->execute([$name, $description, $id]);
                        $success = "Category '$name' updated successfully.";
                    }
                }
            } catch (PDOException $e) {
                $error = "Failed to process category: " . $e->getMessage();
            }
        }
    }
}

// Handle category deletion
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $csrf_token = trim(filter_input(INPUT_POST, 'csrf_token', FILTER_DEFAULT));

if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    die("CSRF token validation failed.");
}

    // Validate CSRF token
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed.";
    } elseif (!$id) {
        $error = "Invalid category ID.";
    } else {
        try {
            // Check if category is used by products
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $stmt->execute([$id]);
            $product_count = $stmt->fetchColumn();

            if ($product_count > 0) {
                $error = "Cannot delete category: it is assigned to $product_count product(s).";
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$id]);
                $success = "Category deleted successfully.";
            }
        } catch (PDOException $e) {
            $error = "Failed to delete category: " . $e->getMessage();
        }
    }
}

// Fetch all categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Fetch category for editing (if applicable)
$edit_category = null;
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $edit_category = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="../assets/css/styles.css" rel="stylesheet">  
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Manage Categories</h2>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add/Edit Category Form -->
        <h4><?php echo $edit_category ? 'Edit Category' : 'Add New Category'; ?></h4>
        <form method="POST" action="" class="admin-card m-4 p-4">
<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            <input type="hidden" name="action" value="<?php echo $edit_category ? 'edit' : 'add'; ?>">
            <?php if ($edit_category): ?>
                <input type="hidden" name="id" value="<?php echo $edit_category['id']; ?>">
            <?php endif; ?>
            <div class="mb-3">
                <label for="name" class="form-label">Category Name</label>
                <input type="text" name="name" id="name" class="form-control" value="<?php echo $edit_category ? htmlspecialchars($edit_category['name']) : ''; ?>" required>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Description</label>
                <textarea name="description" id="description" class="form-control"><?php echo $edit_category ? htmlspecialchars($edit_category['description']) : ''; ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary"><?php echo $edit_category ? 'Update Category' : 'Add Category'; ?></button>
            <?php if ($edit_category): ?>
                <a href="categories.php" class="btn btn-secondary">Cancel</a>
            <?php endif; ?>
        </form>

        <!-- Category List -->
        <h4 class="mt-5">Category List</h4>
        <div class="table-responsive m-4 p-4">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo $category['id']; ?></td>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td><?php echo htmlspecialchars($category['description'] ?: 'N/A'); ?></td>
                        <td>
                            <a href="categories.php?action=edit&id=<?php echo $category['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-warning">Edit</a>
                            <a href="categories.php?action=delete&id=<?php echo $category['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this category?');">Delete</a>
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