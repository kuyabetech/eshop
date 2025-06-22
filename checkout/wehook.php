<?php
require '../includes/config.php';
require '../vendor/autoload.php';

\Stripe\Stripe::setApiKey(STRIPE_SECRET_KEY);

// Retrieve webhook payload
$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$endpoint_secret = 'whsec_...'; // Get from Stripe Dashboard

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sig_header, $endpoint_secret);
    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;
        // Process order as in success.php
        // Log event for debugging
    }
    http_response_code(200);
} catch (\Exception $e) {
    http_response_code(400);
    exit("Webhook error: " . $e->getMessage());
}
?>