<?php
require '../includes/config.php';

// Get tracking number
$tracking_number = filter_input(INPUT_GET, 'tracking_number', FILTER_SANITIZE_STRING);
if (!$tracking_number) {
    header("Location: orders.php?error=Invalid tracking number");
    exit;
}
// Fetch order with tracking number
$stmt = $pdo->prepare("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id WHERE o.tracking_number = ? AND o.user_id = ?");
$stmt->execute([$tracking_number, $_SESSION['user_id'] ?? 0]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: orders.php?error=Order not found or unauthorized");
    exit;
}
// Only fetch tracking info if order is shipped
if ($order['status'] === 'shipped') {
    $tracking_url = "https://api.shippingprovider.com/track/" . urlencode($order['tracking_number']);
    $response = @file_get_contents($tracking_url); // Use cURL in production
    $tracking_data = $response ? json_decode($response, true) : null;
    if ($tracking_data && isset($tracking_data['status'])) {
        echo "<p><strong>Latest Status:</strong> " . htmlspecialchars($tracking_data['status']) . "</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <title>Track Order</title>
    <style>
        /* Apply Poppins font to the entire page */
body {
    font-family: 'Poppins', sans-serif;
}

/* Style the container */
.track-order-container {
    padding: 2rem;
    background: #ffffff;
}

/* Style the card */
.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.card-body {
    padding: 1.5rem;
}

/* Style headings */
h2, h5.card-title {
    color: #f85606; /* Temu orange */
    font-weight: 600;
}

/* Style paragraphs and strong text */
p, strong {
    font-size: 1rem;
    color: #333333;
}

strong {
    font-weight: 600;
}

/* Highlight the tracking number */
.tracking-number {
    color: #f85606;
    font-weight: 600;
}

/* Style the progress bar */
.progress {
    height: 1.5rem;
    border-radius: 8px;
    background-color: #e9ecef;
    margin-top: 1.5rem;
}

.progress-bar {
    transition: width 0.4s ease-in-out;
}

/* Customize progress bar colors based on status */
.progress-bar.bg-warning {
    background-color: #f85606 !important; /* Orange for pending */
}

.progress-bar.bg-info {
    background-color: #6f42c1 !important; /* Purple for processing */
}

.progress-bar.bg-primary {
    background-color: #007bff !important; /* Blue for shipped */
}

.progress-bar.bg-success {
    background-color: #28a745 !important; /* Green for delivered */
}

.progress-bar.bg-secondary {
    background-color: #6c757d !important; /* Gray for others */
}

/* Style buttons */
.btn-primary {
    background-color: #f85606;
    border-color: #f85606;
    color: #ffffff;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 500;
    transition: background-color 0.2s ease;
}

.btn-primary:hover {
    background-color: #d94a05; /* Darker orange on hover */
    border-color: #d94a05;
}

.btn-secondary {
    background-color: #6c757d;
    border-color: #6c757d;
    color: #ffffff;
    border-radius: 8px;
    padding: 0.5rem 1rem;
    font-weight: 500;
    transition: background-color 0.2s ease;
}

.btn-secondary:hover {
    background-color: #5a6268; /* Darker gray on hover */
    border-color: #5a6268;
}

/* Responsive adjustments for smaller screens */
@media (max-width: 768px) {
    .track-order-container {
        padding: 1rem;
    }

    .card-body {
        padding: 1rem;
    }

    h2 {
        font-size: 1.5rem;
    }

    p, strong {
        font-size: 0.9rem;
    }

    .progress {
        height: 1.2rem;
    }

    .btn {
        font-size: 0.9rem;
        padding: 0.4rem 0.8rem;
    }
}
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5 track-order-container">
        <h2>Track Order #<?php echo $order['id']; ?></h2>
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Order Details</h5>
                <p><strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                <p><strong>Total:</strong> $<?php echo number_format($order['total'], 2); ?></p>
                <p><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>
                <p><strong>Tracking Number:</strong> <span class="tracking-number"><?php echo htmlspecialchars($order['tracking_number']); ?></span></p>
                <h5 class="mt-4">Tracking Information</h5>
                <p>
                    Your order is currently <strong><?php echo ucfirst($order['status']); ?></strong>.
                    <?php if ($order['status'] === 'shipped'): ?>
                        Check with your shipping provider using the tracking number above for real-time updates.
                        <a href="https://www.shippingprovider.com/track/<?php echo urlencode($order['tracking_number']); ?>" target="_blank" class="btn btn-primary">Track with Provider</a>
                    <?php else: ?>
                        Tracking details will be available once the order is shipped.
                    <?php endif; ?>
                </p>
            </div>
            <div class="progress mt-4">
                <div class="progress-bar bg-<?php
                    switch ($order['status']) {
                        case 'pending': echo 'warning'; break;
                        case 'processing': echo 'info'; break;
                        case 'shipped': echo 'primary'; break;
                        case 'delivered': echo 'success'; break;
                        default: echo 'secondary';
                    }
                ?>" role="progressbar" style="width: <?php
                    switch ($order['status']) {
                        case 'pending': echo '25%'; break;
                        case 'processing': echo '50%'; break;
                        case 'shipped': echo '75%'; break;
                        case 'delivered': echo '100%'; break;
                        default: echo '0%';
                    }
                ?>;" aria-valuenow="<?php
                    switch ($order['status']) {
                        case 'pending': echo '25'; break;
                        case 'processing': echo '50'; break;
                        case 'shipped': echo '75'; break;
                        case 'delivered': echo '100'; break;
                        default: echo '0';
                    }
                ?>" aria-valuemin="0" aria-valuemax="100">%</div>
            </div>
            
        </div>
        <a href="orders.php" class="btn btn-secondary mt-3">Back to Orders</a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>