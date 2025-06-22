<?php
require '../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
$order = null;

if ($order_id && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT o.*, u.email, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ? AND o.user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch();

    if ($order) {
        $stmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
        $stmt->execute([$order_id]);
        $order_items = $stmt->fetchAll();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - E-Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5 mb-5">
        <h2 class="mb-4" style="color: var(--temu-primary);">Order Confirmation</h2>
        <?php if (!$order): ?>
            <div class="alert alert-danger">Invalid order or unauthorized access.</div>
        <?php else: ?>
            <div class="card p-4 shadow-sm">
                <h4 class="mb-3">Thank You, <?php echo htmlspecialchars($order['username']); ?>!</h4>
                <p>Your order <strong>#<?php echo $order['id']; ?></strong> has been placed successfully.</p>
                <h5 class="mt-4">Order Details</h5>
                <ul class="list-group list-group-flush">
                    <?php foreach ($order_items as $item): ?>
                        <li class="list-group-item d-flex justify-content-between">
                            <span><?php echo htmlspecialchars($item['name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                            <span>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                        </li>
                    <?php endforeach; ?>
                    <li class="list-group-item d-flex justify-content-between fw-bold">
                        <span>Total</span>
                        <span>$<?php echo number_format($order['total'], 2); ?></span>
                    </li>
                </ul>
                <h5 class="mt-4">Payment Instructions</h5>
                <?php if ($order['payment_method'] == 'bank_transfer'): ?>
                    <p>Please transfer <strong>$<?php echo number_format($order['total'], 2); ?></strong> to:</p>
                    <ul>
                        <li>Bank: Example Bank</li>
                        <li>Account Number: 1234567890</li>
                        <li>Routing Number: 0987654321</li>
                        <li>Reference: Order #<?php echo $order['id']; ?></li>
                    </ul>
                    <p>Send proof of payment to <a href="mailto:support@yourdomain.com">support@yourdomain.com</a>.</p>
                <?php else: ?>
                    <p>Please have <strong>$<?php echo number_format($order['total'], 2); ?></strong> ready upon delivery.</p>
                <?php endif; ?>
                <a href="../index.php" class="btn btn-primary mt-3">Continue Shopping</a>
            </div>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/scripts.js"></script>
</body>
</html>