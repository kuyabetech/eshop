<?php
require '../includes/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Canceled</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Payment Canceled</h2>
        <div class="alert alert-warning">Your payment was canceled. You can try again or continue shopping.</div>
        <a href="cart.php" class="btn btn-primary">Return to Cart</a>
        <a href="../index.php" class="btn btn-secondary">Continue Shopping</a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>