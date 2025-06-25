<?php
require '../includes/config.php';
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}
// Initialize feedback
$success = null;
$error = null;

// Only generate CSRF token for GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $tracking_number = filter_input(INPUT_POST, 'tracking_number', FILTER_SANITIZE_STRING);
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed.";
    } elseif (!$order_id || !in_array($status, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
        $error = "Invalid order ID or status.";
    } elseif ($status === 'shipped' && !$tracking_number) {
        $error = "Tracking number is required for shipped status.";
    } else {
        try {
            // Check if order exists
            $checkStmt = $pdo->prepare("SELECT status, tracking_number FROM orders WHERE id = ?");
            $checkStmt->execute([$order_id]);
            $existingOrder = $checkStmt->fetch();
            if (!$existingOrder) {
                $error = "Order not found. (ID: $order_id)";
            } else {
                $currentStatus = ($existingOrder['status'] && trim($existingOrder['status']) !== '') ? $existingOrder['status'] : 'pending';
                $currentTracking = $existingOrder['tracking_number'] ?? '';
                // Always allow update if the new status is different from the current (including blank/null to valid)
                $shouldUpdate = ($currentStatus !== $status) || ($currentTracking !== $tracking_number);
                if ($shouldUpdate) {
                    $stmt = $pdo->prepare("UPDATE orders SET status = ?, tracking_number = ? WHERE id = ?");
                    $stmt->execute([$status, $tracking_number ?: null, $order_id]);
                    $affected = $stmt->rowCount();
                    if ($affected > 0) {
                        $success = "Order status updated successfully.";
                        // Regenerate CSRF token only after a successful update
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    } else {
                        $errorInfo = $stmt->errorInfo();
                        $error = "Order update failed. SQL error: " . ($errorInfo[2] ?? 'Unknown error.');
                    }
                } else {
                    $error = "No changes made. The order already has this status and tracking number.";
                }

                // Send email if shipped
                if ($status === 'processing' || $status === 'shipped' || $status === 'delivered' || $status === 'cancelled') {
                    $stmt = $pdo->prepare("SELECT u.email, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?");
                    $stmt->execute([$order_id]);
                    $user = $stmt->fetch();
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                        $mail->addAddress($user['email'], $user['username']);
                        $mail->isHTML(true);
                        $mail->Subject = "Order #$order_id " . htmlspecialchars($status);
                        $mail->Body = "<div style='font-family:Poppins,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f8f9fa;padding:24px;'>"
    . "<div style='background:#fff;border-radius:12px;padding:24px;max-width:500px;margin:auto;border:1px solid #eee;'>"
    . "<h2 style='color:#f85606;margin-bottom:16px;'>Your Order Has " . htmlspecialchars($status) . "!</h2>"
    . "<p style='font-size:16px;color:#333333;'>Dear <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>"
    . "<p style='font-size:15px;color:#333333;'>Your order <strong>#$order_id</strong> has been <strong>" . htmlspecialchars($status) . "</strong>.</p>"
    . "<hr style='margin:16px 0;border-color:#f8f9fa;'>"
    . "<p style='font-size:15px;'><strong>Tracking Number:</strong> <span style='color:#6f42c1;'>" . htmlspecialchars($tracking_number) . "</span></p>"
    . "<p style='font-size:15px;'><strong>Status:</strong> <span style='color:#f85606;'>" . htmlspecialchars($status) . "</span></p>"
    . "<p style='font-size:15px;color:#333333;'>You can track your shipment with your shipping provider using the tracking number above.</p>"
    . "<div style='margin-top:24px;text-align:center;'><a href='" . htmlspecialchars(BASE_URL . "user/track_orders.php?tracking_number=" . urlencode($tracking_number)) . "' style='background:#f85606;color:#fff;padding:10px 24px;border-radius:12px;text-decoration:none;font-weight:500;'>Track Order</a></div>"
    . "<hr style='margin:24px 0;border-color:#f8f9fa;'>"
    . "<p style='font-size:13px;color:#888;'>Thank you for shopping with us!<br>E-Shop Team</p>"
    . "</div>"
    . "</div>";
                        $mail->send();
                        $success .= " Email sent to user.";
                    } catch (Exception $e) {
                        $error = "Order updated, but email sending failed: " . $mail->ErrorInfo;
                        error_log("Email sending failed: " . $mail->ErrorInfo);
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Failed to update order status: " . $e->getMessage();
        }
    }
}

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
// Fix: Inject LIMIT/OFFSET as integers, not bound params
$sql = "SELECT o.*, u.username, COUNT(oi.id) as item_count FROM orders o LEFT JOIN users u ON o.user_id = u.id LEFT JOIN order_items oi ON o.id = oi.order_id GROUP BY o.id ORDER BY o.created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$orders = $stmt->fetchAll();
?>

     <link href="../assets/css/styles.css" rel="stylesheet">  

    <?php include '../includes/header.php'; ?>
    <div class="container admin-container mt-5">
        <h2>Manage Orders</h2>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="admin-table table-striped">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>User</th>
                        <th>Date</th>
                        <th>Total</th>
                        <th>Items</th>
                        <th>Status</th>
                        <th>Tracking</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><?php echo htmlspecialchars($order['username']); ?></td>
                        <td><?php echo date('F j, Y', strtotime($order['created_at'])); ?></td>
                        <td>&#8358;<?php echo number_format($order['total'], 2); ?></td>
                        <td><?php echo $order['item_count']; ?></td>
                        <td>
                            <form method="POST" action="" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <select name="status" class="form-select d-inline-block w-auto" onchange="this.form.submit()">
                                    <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                                    <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                    <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                    <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                                <input type="text" name="tracking_number" class="form-control d-inline-block w-50 mt-2" placeholder="Tracking Number" value="<?php echo isset($order['tracking_number']) ? htmlspecialchars($order['tracking_number']) : ''; ?>" <?php echo (isset($order['status']) && $order['status'] === 'shipped') ? 'required' : ''; ?> >
                            </form>
                        </td>
                        <td><?php echo isset($order['tracking_number']) && $order['tracking_number'] ? htmlspecialchars($order['tracking_number']) : 'N/A'; ?></td>
                        <td>
                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">View Details</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script>
    // Require tracking number if status is shipped
    document.querySelectorAll('form.d-inline').forEach(function(form) {
        var statusSelect = form.querySelector('select[name="status"]');
        var trackingInput = form.querySelector('input[name="tracking_number"]');
        statusSelect.addEventListener('change', function(e) {
            if (statusSelect.value === 'shipped') {
                trackingInput.required = true;
                if (!trackingInput.value) {
                    trackingInput.focus();
                }
            } else {
                trackingInput.required = false;
            }
        });
        form.addEventListener('submit', function(e) {
            if (statusSelect.value === 'shipped' && !trackingInput.value) {
                e.preventDefault();
                trackingInput.focus();
                alert('Tracking number is required for shipped status.');
            }
        });
    });
    </script>
</body>
</html>