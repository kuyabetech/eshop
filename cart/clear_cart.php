<?php
require '../includes/config.php';

// Clear the cart
unset($_SESSION['cart']);

// Redirect to cart with success message
header("Location: cart.php?success=Cart cleared successfully");
exit;
?>