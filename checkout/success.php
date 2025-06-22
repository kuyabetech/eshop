<?php
require '../includes/config.php';
require '../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php?error=Please log in");
    exit;
}

// Set Stripe API key
\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Get session ID from query string
$session_id = filter_input(INPUT_GET, 'session_id', FILTER_SANITIZE_STRING);
if (!$session_id) {
    header("Location: cart.php?error=Invalid session ID");
    exit;
}

// Retrieve Stripe Checkout Session
try {
    $session = \Stripe\Checkout\Session::retrieve($session_id);
    if ($session->payment_status === 'paid' && $session->client_reference_id == $_SESSION['user_id']) {
        // Save order to database
        $pdo->beginTransaction();
        $total = $session->amount_total / 100;
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, status) VALUES (?, ?, 'completed')");
        $stmt->execute([$_SESSION['user_id'], $total]);
        $order_id = $pdo->lastInsertId();

        $items = [];
        foreach ($session->display_items as $item) {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE name = ?");
            $stmt->execute([$item->custom->name]);
            $product = $stmt->fetch();

            if ($product) {
                $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $product['id'], $item->quantity, $item->amount / 100]);
                $items[] = [
                    'name' => $item->custom->name,
                    'quantity' => $item->quantity,
                    'price' => $item->amount / 100,
                ];

                // Update stock
                $stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
                $stmt->execute([$item->quantity, $product['id']]);
            }
        }

        $pdo->commit();
        unset($_SESSION['cart']);
        unset($_SESSION['discount_code']);
        $success = "Payment successful! Order #$order_id has been placed.";

        // Fetch user email
        $stmt = $pdo->prepare("SELECT email, username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        // Send email notification
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
            $mail->Subject = "Order Confirmation - Order #$order_id";
            $mail->Body = "
                <h2>Thank You for Your Order!</h2>
                <p>Dear {$user['username']},</p>
                <p>Your order #$order_id has been placed successfully.</p>
                <h3>Order Details</h3>
                <table border='1' cellpadding='5'>
                    <tr><th>Product</th><th>Quantity</th><th>Price</th></tr>";
            foreach ($items as $item) {
                $mail->Body .= "<tr><td>" . htmlspecialchars($item['name']) . "</td><td>{$item['quantity']}</td><td>$" . number_format($item['price'], 2) . "</td></tr>";
            }
            $mail->Body .= "</table>
                <p><strong>Total:</strong> $" . number_format($total, 2) . "</p>
                <p>Thank you for shopping with us!</p>";
            $mail->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $mail->ErrorInfo);
        }
    } else {
        $error = "Payment not completed.";
    }
} catch (\Stripe\Exception\ApiErrorException $e) {
    $error = "Error verifying payment: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Payment Status</h2>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <a href="../index.php" class="btn btn-primary">Continue Shopping</a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>