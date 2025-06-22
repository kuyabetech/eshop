<?php
require '../includes/config.php';

// Ensure the product ID is provided and valid
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id) {
    header("Location: ../products.php?error=Invalid product");
    exit;
}

// Check if the product exists and has stock
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND stock > 0");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: ../products.php?error=Product not available");
    exit;
}

// Initialize cart if not already set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Add or update product in cart
$quantity = filter_input(INPUT_GET, 'quantity', FILTER_VALIDATE_INT) ?: 1;
if ($quantity < 1) {
    $quantity = 1;
}

// Ensure quantity doesn't exceed available stock
if ($quantity > $product['stock']) {
    header("Location: ../products.php?error=Quantity exceeds stock");
    exit;
}

$_SESSION['cart'][$product_id] = isset($_SESSION['cart'][$product_id])
    ? $_SESSION['cart'][$product_id] + $quantity
    : $quantity;

// Redirect back to products page with success message
header("Location: ../products.php?success=Product added to cart");
exit;
?>