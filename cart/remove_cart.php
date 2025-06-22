<?php
// Include configuration and start session
require '../includes/config.php';

// Ensure the product ID is provided and valid
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id) {
    header("Location: ../cart/cart.php?error=Invalid product ID");
    exit;
}

// Check if the product exists in the cart
if (isset($_SESSION['cart'][$product_id])) {
    // Remove the product from the cart
    unset($_SESSION['cart'][$product_id]);
    
    // If cart is empty, unset the cart session
    if (empty($_SESSION['cart'])) {
        unset($_SESSION['cart']);
    }
    
    // Redirect back to cart with success message
    header("Location: ../cart/cart.php?success=Product removed from cart");
} else {
    // Redirect with error if product not in cart
    header("Location: ../cart/cart.php?error=Product not in cart");
}
exit;
?>