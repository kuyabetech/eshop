<?php
// Include configuration and start session
require '../includes/config.php';

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

// Get order ID from query string
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$order_id) {
    header("Location: orders.php?error=Invalid order ID");
    exit;
}

// Fetch order details
$stmt = $pdo->prepare("SELECT o.*, u.username, u.email FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: orders.php?error=Order not found");
    exit;
}

// Fetch order items
$stmt = $pdo->prepare("SELECT oi.*, p.name FROM order_items oi JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt->execute([$order_id]);
$order_items = $stmt->fetchAll();
?>

    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Order Details - #<?php echo htmlspecialchars($order['id']); ?></h2>
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Order Information</h5>
                <p><strong>User:</strong> <?php echo htmlspecialchars($order['username']); ?> (<?php echo htmlspecialchars($order['email']); ?>)</p>
                <p><strong>Order Date:</strong> <?php echo date('F j, Y, g:i A', strtotime($order['created_at'])); ?></p>
                <p><strong>Total:</strong> $<?php echo number_format($order['total'], 2); ?></p>
                <p><strong>Status:</strong> <?php echo htmlspecialchars(ucfirst($order['status'])); ?></p>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Billing Details</h6>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($order['billing_first_name'] . ' ' . $order['billing_last_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($order['billing_email']); ?></p>
                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['billing_phone']); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($order['billing_address']); ?></p>
                        <p><strong>City:</strong> <?php echo htmlspecialchars($order['billing_city']); ?></p>
                        <p><strong>Postal Code:</strong> <?php echo htmlspecialchars($order['billing_postal_code']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <h6>Shipping Details</h6>
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($order['shipping_first_name'] . ' ' . $order['shipping_last_name']); ?></p>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($order['shipping_address']); ?></p>
                        <p><strong>City:</strong> <?php echo htmlspecialchars($order['shipping_city']); ?></p>
                        <p><strong>Postal Code:</strong> <?php echo htmlspecialchars($order['shipping_postal_code']); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <h4>Order Items</h4>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($order_items as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>&#8358;<?php echo number_format($item['price'], 2); ?></td>
                        <td>&#8358;<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="orders.php" class="btn btn-primary">Back to Orders</a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>