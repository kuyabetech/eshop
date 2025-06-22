<?php
require '../includes/config.php';
require '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../user/login.php?redirect=checkout/checkout.php");
    exit;
}

$cart = $_SESSION['cart'] ?? [];
$cart_items = [];
$total = 0;
$discount = 0;
$discount_code = null;

if (!empty($cart)) {
    $placeholders = implode(',', array_fill(0, count($cart), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, stock, image FROM products WHERE id IN ($placeholders)");
    $stmt->execute(array_keys($cart));
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($cart_items as &$item) {
        $item['quantity'] = $cart[$item['id']];
        $item['subtotal'] = $item['price'] * $item['quantity'];
        $total += $item['subtotal'];
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_discount'])) {
    $code = filter_input(INPUT_POST, 'discount_code', FILTER_SANITIZE_STRING);
    $stmt = $pdo->prepare("SELECT id, discount_percentage FROM discount_codes WHERE code = ? AND valid_from <= NOW() AND valid_until >= NOW() AND (max_uses = 0 OR uses < max_uses)");
    $stmt->execute([$code]);
    $discount_data = $stmt->fetch();

    if ($discount_data) {
        $discount = ($discount_data['discount_percentage'] / 100) * $total;
        $discount_code = $code;
        $_SESSION['discount_code_id'] = $discount_data['id'];
    } else {
        $discount_error = "Invalid or expired discount code.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['place_order'])) {
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);
    $billing_first_name = filter_input(INPUT_POST, 'billing_first_name', FILTER_SANITIZE_STRING);
    $billing_last_name = filter_input(INPUT_POST, 'billing_last_name', FILTER_SANITIZE_STRING);
    $billing_email = filter_input(INPUT_POST, 'billing_email', FILTER_SANITIZE_EMAIL);
    $billing_phone = filter_input(INPUT_POST, 'billing_phone', FILTER_SANITIZE_STRING);
    $billing_address = filter_input(INPUT_POST, 'billing_address', FILTER_SANITIZE_STRING);
    $billing_city = filter_input(INPUT_POST, 'billing_city', FILTER_SANITIZE_STRING);
    $billing_postal_code = filter_input(INPUT_POST, 'billing_postal_code', FILTER_SANITIZE_STRING);
    $shipping_first_name = filter_input(INPUT_POST, 'shipping_first_name', FILTER_SANITIZE_STRING);
    $shipping_last_name = filter_input(INPUT_POST, 'shipping_last_name', FILTER_SANITIZE_STRING);
    $shipping_address = filter_input(INPUT_POST, 'shipping_address', FILTER_SANITIZE_STRING);
    $shipping_city = filter_input(INPUT_POST, 'shipping_city', FILTER_SANITIZE_STRING);
    $shipping_postal_code = filter_input(INPUT_POST, 'shipping_postal_code', FILTER_SANITIZE_STRING);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);

    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed.";
    } elseif (!$billing_first_name || !$billing_last_name || !$billing_email || !$billing_phone || !$billing_address || !$billing_city || !$billing_postal_code || !$payment_method || !in_array($payment_method, ['bank_transfer', 'cash_on_delivery'])) {
        $error = "Please fill in all required billing fields correctly.";
    } else {
        try {
            $pdo->beginTransaction();

            $final_total = $total - $discount;
            $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, status, payment_method, payment_status, billing_first_name, billing_last_name, billing_email, billing_phone, billing_address, billing_city, billing_postal_code, shipping_first_name, shipping_last_name, shipping_address, shipping_city, shipping_postal_code, created_at) VALUES (?, ?, 'pending', ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $_SESSION['user_id'], $final_total, $payment_method,
                $billing_first_name, $billing_last_name, $billing_email, $billing_phone, $billing_address, $billing_city, $billing_postal_code,
                $shipping_first_name ?: $billing_first_name, $shipping_last_name ?: $billing_last_name, $shipping_address ?: $billing_address, $shipping_city ?: $billing_city, $shipping_postal_code ?: $billing_postal_code
            ]);
            $order_id = $pdo->lastInsertId();

            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stock_stmt = $pdo->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
            foreach ($cart_items as $item) {
                $stmt->execute([$order_id, $item['id'], $item['quantity'], $item['price']]);
                $stock_stmt->execute([$item['quantity'], $item['id'], $item['quantity']]);
            }

            if (isset($_SESSION['discount_code_id'])) {
                $stmt = $pdo->prepare("UPDATE discount_codes SET uses = uses + 1 WHERE id = ?");
                $stmt->execute([$_SESSION['discount_code_id']]);
                unset($_SESSION['discount_code_id']);
            }

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
                $mail->addAddress($billing_email, "$billing_first_name $billing_last_name");
                $mail->isHTML(true);
                $mail->Subject = "Order Confirmation - E-Shop";
                $body = "<div style='font-family:Poppins,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f8f9fa;padding:24px;'>"
                    . "<div style='background:#fff;border-radius:12px;padding:24px;max-width:500px;margin:auto;border:1px solid #eee;'>"
                    . "<h2 style='color:#f85606;margin-bottom:16px;'>Thank You for Your Order!</h2>"
                    . "<p style='font-size:16px;color:#333333;'>Order ID: <strong style='color:#6f42c1;'>#$order_id</strong></p>"
                    . "<p style='font-size:15px;color:#333333;'>Total: <strong style='color:#f85606;'>$" . number_format($final_total, 2) . "</strong></p>"
                    . "<h3 style='color:#007bff;margin-top:24px;'>Payment Instructions</h3>";
                if ($payment_method == 'bank_transfer') {
                    $body .= "<p style='font-size:15px;color:#333333;'>Please transfer <strong style='color:#f85606;'>$" . number_format($final_total, 2) . "</strong> to:<br>"
                        . "<span style='color:#6f42c1;'>Bank: Example Bank<br>Account Number: 1234567890<br>Routing Number: 0987654321<br>Reference: Order #$order_id</span></p>";
                } else {
                    $body .= "<p style='font-size:15px;color:#333333;'>Cash on Delivery: Have <strong style='color:#f85606;'>$" . number_format($final_total, 2) . "</strong> ready upon delivery.</p>";
                }
                $body .= "<p style='font-size:15px;color:#333333;'>We'll notify you once your payment is confirmed.</p>"
                    . "<hr style='margin:24px 0;border-color:#f8f9fa;'>"
                    . "<p style='font-size:13px;color:#888;'>Thank you for shopping with us!<br>E-Shop Team</p>"
                    . "</div>"
                    . "</div>";
                $mail->Body = $body;
                $mail->send();
            } catch (Exception $e) {
                error_log("Email sending failed: " . $mail->ErrorInfo);
            }

            unset($_SESSION['cart']);
            $pdo->commit();
            header("Location: order_confirmation.php?order_id=$order_id");
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Checkout error: " . $e->getMessage());
            $error = "An error occurred: " . $e->getMessage();
        }
    }
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - E-Shop</title>
    <link href="[invalid url, do not cite] rel="stylesheet">
    <link href="[invalid url, do not cite] rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5 mb-5">
        <h2 class="mb-4" style="color: var(--temu-primary);">Checkout</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (empty($cart_items)): ?>
            <div class="alert alert-info">Your cart is empty. <a href="../index.php" class="alert-link">Continue shopping</a>.</div>
        <?php else: ?>
            <div class="row">
                <div class="col-lg-4 order-lg-last mb-4">
                    <div class="card p-4 shadow-sm">
                        <h4 class="mb-3">Order Summary</h4>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($cart_items as $item): ?>
                                <li class="list-group-item d-flex justify-content-between">
                                    <span><?php echo htmlspecialchars($item['name']); ?> (x<?php echo $item['quantity']; ?>)</span>
                                    <span>$<?php echo number_format($item['subtotal'], 2); ?></span>
                                </li>
                            <?php endforeach; ?>
                            <li class="list-group-item d-flex justify-content-between">
                                <span>Subtotal</span>
                                <span>$<?php echo number_format($total, 2); ?></span>
                            </li>
                            <?php if ($discount > 0): ?>
                                <li class="list-group-item d-flex justify-content-between text-success">
                                    <span>Discount (<?php echo htmlspecialchars($discount_code); ?>)</span>
                                    <span>-$<?php echo number_format($discount, 2); ?></span>
                                </li>
                            <?php endif; ?>
                            <li class="list-group-item d-flex justify-content-between fw-bold">
                                <span>Total</span>
                                <span>$<?php echo number_format($total - $discount, 2); ?></span>
                            </li>
                        </ul>
                        <form method="POST" action="" class="mt-3" id="discount-form">
                            <div class="input-group">
                                <input type="text" name="discount_code" class="form-control" placeholder="Discount Code">
                                <button type="submit" name="apply_discount" class="btn btn-primary">Apply</button>
                            </div>
                            <?php if (isset($discount_error)): ?>
                                <div class="text-danger small mt-1"><?php echo htmlspecialchars($discount_error); ?></div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <div class="col-lg-8">
                    <div class="card p-4 shadow-sm">
                        <form method="POST" action="" id="checkout-form" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <h4 class="mb-3">Billing Details</h4>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="billing_first_name" class="form-label">First Name</label>
                                    <input type="text" name="billing_first_name" id="billing_first_name" class="form-control" placeholder="First Name" required>
                                    <div class="invalid-feedback">First name is required.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="billing_last_name" class="form-label">Last Name</label>
                                    <input type="text" name="billing_last_name" id="billing_last_name" class="form-control" placeholder="Last Name" required>
                                    <div class="invalid-feedback">Last name is required.</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="billing_email" class="form-label">Email</label>
                                <input type="email" name="billing_email" id="billing_email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="Email" required>
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>
                            <div class="mb-3">
                                <label for="billing_phone" class="form-label">Phone</label>
                                <input type="text" name="billing_phone" id="billing_phone" class="form-control" placeholder="Phone Number" required>
                                <div class="invalid-feedback">Phone number is required.</div>
                            </div>
                            <div class="mb-3">
                                <label for="billing_address" class="form-label">Address</label>
                                <input type="text" name="billing_address" id="billing_address" class="form-control" placeholder="Street Address" required>
                                <div class="invalid-feedback">Address is required.</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="billing_city" class="form-label">City</label>
                                    <input type="text" name="billing_city" id="billing_city" class="form-control" placeholder="City" required>
                                    <div class="invalid-feedback">City is required.</div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="billing_postal_code" class="form-label">Postal Code</label>
                                    <input type="text" name="billing_postal_code" id="billing_postal_code" class="form-control" placeholder="Postal Code" required>
                                    <div class="invalid-feedback">Postal code is required.</div>
                                </div>
                            </div>
                            <h4 class="mb-3 mt-4">Shipping Details</h4>
                            <div class="form-check mb-3">
                                <input type="checkbox" class="form-check-input" id="same_as_billing" name="same_as_billing">
                                <label class="form-check-label" for="same_as_billing">Same as billing address</label>
                            </div>
                            <div id="shipping_fields">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="shipping_first_name" class="form-label">First Name</label>
                                        <input type="text" name="shipping_first_name" id="shipping_first_name" class="form-control" placeholder="First Name">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="shipping_last_name" class="form-label">Last Name</label>
                                        <input type="text" name="shipping_last_name" id="shipping_last_name" class="form-control" placeholder="Last Name">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="shipping_address" class="form-label">Address</label>
                                    <input type="text" name="shipping_address" id="shipping_address" class="form-control" placeholder="Street Address">
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="shipping_city" class="form-label">City</label>
                                        <input type="text" name="shipping_city" id="shipping_city" class="form-control" placeholder="City">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="shipping_postal_code" class="form-label">Postal Code</label>
                                        <input type="text" name="shipping_postal_code" id="shipping_postal_code" class="form-control" placeholder="Postal Code">
                                    </div>
                                </div>
                            </div>
                            <h4 class="mb-3 mt-4">Payment Method</h4>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input type="radio" name="payment_method" id="bank_transfer" value="bank_transfer" class="form-check-input" required>
                                    <label for="bank_transfer" class="form-check-label">Bank Transfer</label>
                                    <p class="small text-muted">Transfer payment to our bank account. Details provided after order confirmation.</p>
                                </div>
                                <div class="form-check">
                                    <input type="radio" name="payment_method" id="cash_on_delivery" value="cash_on_delivery" class="form-check-input" required>
                                    <label for="cash_on_delivery" class="form-check-label">Cash on Delivery</label>
                                    <p class="small text-muted">Pay when your order is delivered.</p>
                                </div>
                                <div class="invalid-feedback d-block">Please select a payment method.</div>
                            </div>
                            <button type="submit" name="place_order" class="btn btn-primary w-100">Place Order</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="[invalid url, do not cite]"></script>
    <script src="[invalid url, do not cite]"></script>
    <script src="../assets/js/scripts.js"></script>
    <script>
        // Toggle shipping fields based on checkbox
        document.getElementById('same_as_billing').addEventListener('change', function() {
            const shippingFields = document.getElementById('shipping_fields');
            shippingFields.style.display = this.checked ? 'none' : 'block';
            const inputs = shippingFields.querySelectorAll('input');
            inputs.forEach(input => input.required = !this.checked);
        });
    </script>
</body>
</html>