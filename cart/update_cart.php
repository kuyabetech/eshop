<?php
require '../includes/config.php';

// Ensure the product ID and quantity are provided
$product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);

if (!$product_id || $quantity < 1) {
    header("Location: ../cart/cart.php?error=Invalid input");
    exit;
}

// Check if the product exists and has sufficient stock
$stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: ../cart/cart.php?error=Product not found");
    exit;
}

if ($quantity > $product['stock']) {
    header("Location: ../cart/cart.php?error=Quantity exceeds stock");
    exit;
}

// Update cart in session
if (isset($_SESSION['cart'][$product_id])) {
    $_SESSION['cart'][$product_id] = $quantity;
} else {
    header("Location: ../cart/cart.php?error=Product not in cart");
    exit;
}

// Redirect back to cart with success message
header("Location: ../cart/cart.php?success=Cart updated");
exit;
?>