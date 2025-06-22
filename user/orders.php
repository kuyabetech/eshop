<?php
require '../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=Please log in to view your orders");
    exit;
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle order cancellation
$success = $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);
    $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);

    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed.";
    } elseif (!$order_id || $order_id < 1) {
        $error = "Invalid order ID.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT status, user_id FROM orders WHERE id = ?");
            $stmt->execute([$order_id]);
            $order = $stmt->fetch();

            if (!$order) {
                $error = "Order not found.";
            } elseif ($order['user_id'] !== $_SESSION['user_id']) {
                $error = "Unauthorized action.";
            } elseif ($order['status'] !== 'pending') {
                $error = "Only pending orders can be cancelled.";
            } else {
                $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$order_id]);
                if ($stmt->rowCount() > 0) {
                    $success = "Order cancelled successfully.";
                    // Invalidate cache if using Redis
                    $redis = new Redis();
                    if ($redis->connect('localhost', 6379)) {
                        $redis->del("user_orders:{$_SESSION['user_id']}:*");
                    }
                } else {
                    $error = "Failed to cancel order.";
                }
            }
        } catch (PDOException $e) {
            error_log("Order cancellation error: " . $e->getMessage());
            $error = "Failed to cancel order. Please try again.";
        }
    }
}

// Pagination and filtering
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
$per_page = 10;
$offset = ($page - 1) * $per_page;
$status_filter = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING);
$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING);

// Build SQL query
$sql = "SELECT o.*, COUNT(oi.id) as item_count 
        FROM orders o 
        LEFT JOIN order_items oi ON o.id = oi.order_id 
        WHERE o.user_id = :user_id";
$params = [':user_id' => $_SESSION['user_id']];

if ($status_filter && in_array($status_filter, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
    $sql .= " AND o.status = :status";
    $params[':status'] = $status_filter;
}
if ($search_query) {
    $sql .= " AND (o.id LIKE :search OR o.tracking_number LIKE :search)";
    $params[':search'] = "%$search_query%";
}
$sql .= " GROUP BY o.id ORDER BY o.created_at DESC LIMIT :limit OFFSET :offset";
$params[':limit'] = (int)$per_page;
$params[':offset'] = (int)$offset;

$stmt = $pdo->prepare($sql);
// Bind values, especially for LIMIT/OFFSET as integers
foreach ($params as $key => $value) {
    $type = ($key === ':limit' || $key === ':offset') ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key, $value, $type);
}
$stmt->execute();
$orders = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - E-Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5 mb-5 orders-container">
        <h2 class="mb-4" style="color: var(--temu-primary);">My Orders</h2>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <!-- Search and Filter -->
        <div class="mb-4">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search by Order ID or Tracking Number" value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo ($status_filter ?? '') === $s ? 'selected' : ''; ?>>
                                <?php echo ucfirst($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
        <?php if (empty($orders)): ?>
            <div class="alert alert-info">No orders found.</div>
        <?php else: ?>
            <div class="card shadow-sm orders-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover orders-table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
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
                                        <td><?php echo date('F j, Y', strtotime($order['created_at'])); ?></td>
                                        <td>$<?php echo number_format($order['total'], 2); ?></td>
                                        <td><?php echo $order['item_count']; ?></td>
                                        <td>
                                            <span class="badge orders-badge bg-<?php
                                                switch ($order['status']) {
                                                    case 'pending': echo 'warning'; break;
                                                    case 'processing': echo 'info'; break;
                                                    case 'shipped': echo 'primary'; break;
                                                    case 'delivered': echo 'success'; break;
                                                    case 'cancelled': echo 'danger'; break;
                                                }
                                            ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                          <?php if (!empty($order['tracking_number'])): ?>
                                    <?php echo htmlspecialchars($order['tracking_number']); ?>
                                    <a href="track_orders.php?tracking_number=<?php echo urlencode($order['tracking_number']); ?>" class="btn btn-sm btn-outline-primary ms-2">Track</a>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-info me-2">View Details</a>
                                            <?php if ($order['status'] === 'pending'): ?>
                                                <form method="POST" action="" class="d-inline cancel-form" id="cancel-form-<?php echo $order['id']; ?>">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                    <input type="hidden" name="action" value="cancel_order">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to cancel this order?');">Cancel</button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php
                            // Calculate total pages for pagination
                            $count_sql = "SELECT COUNT(*) FROM orders WHERE user_id = :user_id";
                            $count_params = [':user_id' => $_SESSION['user_id']];
                            if ($status_filter && in_array($status_filter, ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])) {
                                $count_sql .= " AND status = :status";
                                $count_params[':status'] = $status_filter;
                            }
                            if ($search_query) {
                                $count_sql .= " AND (id LIKE :search OR tracking_number LIKE :search)";
                                $count_params[':search'] = "%$search_query%";
                            }
                            $count_stmt = $pdo->prepare($count_sql);
                            foreach ($count_params as $key => $value) {
                                $count_stmt->bindValue($key, $value, PDO::PARAM_STR);
                            }
                            $count_stmt->execute();
                            $total_orders = $count_stmt->fetchColumn();
                            $total_pages = ceil($total_orders / $per_page);
                        ?>
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Order pagination">
                                <ul class="pagination justify-content-center mt-3">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter ?? ''); ?>&search=<?php echo urlencode($search_query ?? ''); ?>">Previous</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                        <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                            <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter ?? ''); ?>&search=<?php echo urlencode($search_query ?? ''); ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter ?? ''); ?>&search=<?php echo urlencode($search_query ?? ''); ?>">Next</a>
                                    </li>
                                </ul>
                            </nav>
                        <?php endif; ?>
                        <a href="../index.php" class="btn btn-outline-secondary mt-3">Continue Shopping</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php include '../includes/footer.php'; ?>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="../assets/js/scripts.js"></script>
    </body>
    </html>