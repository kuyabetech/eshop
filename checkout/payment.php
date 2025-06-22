<?php
require '../includes/config.php';
require '../vendor/autoload.php';
\Stripe\Stripe::setApiKey('your_stripe_secret_key');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $token = $_POST['stripeToken'];
    $amount = $_SESSION['cart_total'] * 100; // Convert to cents

    try {
        $charge = \Stripe\Charge::create([
            'amount' => $amount,
            'currency' => 'usd',
            'description' => 'E-commerce Order',
            'source' => $token,
        ]);

        // Save order to database
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, total, status) VALUES (?, ?, 'completed')");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['cart_total']]);
        $order_id = $pdo->lastInsertId();

        foreach ($_SESSION['cart'] as $id => $quantity) {
            $stmt = $pdo->prepare("SELECT price FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $price = $stmt->fetchColumn();
            $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$order_id, $id, $quantity, $price]);
        }

        unset($_SESSION['cart']);
        header("Location: order_confirmation.php?order_id=$order_id");
    } catch (\Stripe\Exception\CardException $e) {
        $error = $e->getError()->message;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://js.stripe.com/v3/"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Payment</h2>
        <?php if (isset($error)) echo "<p class='text-danger'>$error</p>"; ?>
        <form id="payment-form" method="POST">
            <div id="card-element" class="form-control"></div>
            <div id="card-errors" class="text-danger"></div>
            <button type="submit" class="btn btn-success mt-3">Pay $<?php echo number_format($_SESSION['cart_total'], 2); ?></button>
        </form>
    </div>
    <script>
        var stripe = Stripe('your_stripe_publishable_key');
        var elements = stripe.elements();
        var card = elements.create('card');
        card.mount('#card-element');
        card.on('change', function(event) {
            var displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
        var form = document.getElementById('payment-form');
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            stripe.createToken(card).then(function(result) {
                if (result.error) {
                    document.getElementById('card-errors').textContent = result.error.message;
                } else {
                    var hiddenInput = document.createElement('input');
                    hiddenInput.setAttribute('type', 'hidden');
                    hiddenInput.setAttribute('name', 'stripeToken');
                    hiddenInput.setAttribute('value', result.token.id);
                    form.appendChild(hiddenInput);
                    form.submit();
                }
            });
        });
    </script>
    <?php include '../includes/footer.php'; ?>
</body>
</html>