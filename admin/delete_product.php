<?php
require '../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=Unauthorized access");
    exit;
}

$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$csrf_token = filter_input(INPUT_GET, 'csrf_token', FILTER_SANITIZE_STRING);

if (!$product_id) {
    header("Location: products.php?error=Invalid product ID");
    exit;
}
if (!isset($_SESSION['csrf_token']) || !$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
    header("Location: products.php?error=CSRF token validation failed");
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    header("Location: products.php?success=Product deleted successfully");
    exit;
} catch (PDOException $e) {
    header("Location: products.php?error=Failed to delete product: " . urlencode($e->getMessage()));
    exit;
}
