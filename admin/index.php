<?php
require '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

// Set default values to avoid undefined variable warnings
$total_orders = 0;
$total_revenue = 0;
$total_users = 0;
$total_products = 0;
$dropship_orders = 0;
$active_suppliers = 0;
$recent_orders = [];
$low_stock_products = [];
$suppliers = [];

try {
    // Total orders
    $stmt = $pdo->query("SELECT COUNT(*) AS total_orders FROM orders");
    $total_orders = $stmt->fetchColumn();

    // Total revenue
    $stmt = $pdo->query("SELECT SUM(total) AS total_revenue FROM orders WHERE status = 'delivered'");
    $total_revenue = $stmt->fetchColumn() ?: 0;

    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) AS total_users FROM users");
    $total_users = $stmt->fetchColumn();

    // Total products
    $stmt = $pdo->query("SELECT COUNT(*) AS total_products FROM products");
    $total_products = $stmt->fetchColumn();

    // Dropshipping metrics
    // $stmt = $pdo->query("SELECT COUNT(*) AS dropship_orders FROM orders WHERE supplier_order_id IS NOT NULL");
    // $dropship_orders = $stmt->fetchColumn();
    $dropship_orders = 0; // Remove this metric if not needed

    $stmt = $pdo->query("SELECT COUNT(*) AS active_suppliers FROM suppliers");
    $active_suppliers = $stmt->fetchColumn();

    // Recent orders
    $stmt = $pdo->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll();

    // Low stock products
    $stmt = $pdo->query("SELECT * FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 5");
    $low_stock_products = $stmt->fetchAll();

    // Fetch suppliers
    $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY name ASC");
    $suppliers = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $error = "Failed to load dashboard data: " . $e->getMessage(); // Show actual error for debugging
    // Ensure variables are set even on error
    $dropship_orders = 0;
    $active_suppliers = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - E-Shop</title>
    <link href="[invalid url, do not cite] rel="stylesheet">
    <link href="[invalid url, do not cite] rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5 mb-5 admin-container">
        <h2 class="mb-4 admin-title" style="color: var(--temu-primary);">Admin Dashboard</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Metrics Section -->
        <div class="row mb-5">
            <div class="col-md-3 mb-3">
                <div class="metric-card">
                    <h3><?php echo $total_orders; ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="metric-card">
                    <h3>&#8358;<?php echo number_format($total_revenue, 2); ?></h3>
                    <p>Total Revenue</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="metric-card">
                    <h3><?php echo $total_users; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="metric-card">
                    <h3><?php echo $total_products; ?></h3>
                    <p>Total Products</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="metric-card">
                    <h3><?php echo $dropship_orders; ?></h3>
                    <p>Dropship Orders</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="metric-card">
                    <h3><?php echo $active_suppliers; ?></h3>
                    <p>Active Suppliers</p>
                </div>
            </div>
        </div>
                <!-- Dropshipping Metrics Chart -->
        <div class="card shadow-sm admin-card mb-4">
            <div class="card-body">
                <canvas id="dropshipChart" height="400"></canvas>
            </div>
        </div>
          <!-- Quick Actions -->
        <div class="row mb-5">
            <div class="col-md-12">
                <h4>Quick Actions</h4>
                <div class="d-flex flex-wrap gap-2">
                    <a href="products.php" class="btn btn-primary">Manage Products</a>
                    <a href="bulk_stock_update.php" class="btn btn-primary">Bulk Stock Update</a>
                    <a href="orders.php" class="btn btn-primary">Manage Orders</a>
                    <a href="inventory.php" class="btn btn-primary">Manage Inventory</a>
                    <a href="categories.php" class="btn btn-primary">Manage Categories</a>
                    <a href="discounts.php" class="btn btn-primary">Manage Discounts</a>
                    <a href="reviews.php" class="btn btn-primary">Manage Reviews</a>
                    <a href="users.php" class="btn btn-primary">Manage Users</a>
                </div>
            </div>
        </div>
        <!-- Supplier Management -->
        <div class="card shadow-sm admin-card mb-4">
            <div class="card-body">
                <h4 class="card-title" style="color: var(--temu-primary);">Manage Suppliers</h4>
                <a href="add_supplier.php" class="btn btn-primary mb-3">Add Supplier</a>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-hover orders-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>API URL</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($suppliers)): ?>
                                <tr><td colspan="3">No suppliers added yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($supplier['name']); ?></td>
                                        <td><?php echo htmlspecialchars($supplier['api_url']); ?></td>
                                        <td>
                                            <a href="edit_supplier.php?id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="delete_supplier.php?id=<?php echo $supplier['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this supplier?');">Delete</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Recent Orders -->
        <div class="card shadow-sm admin-card mb-4">
            <div class="card-body">
                <h4 class="card-title" style="color: var(--temu-primary);">Recent Orders</h4>
                <div class="table-responsive">
                    <table class="table table-hover orders-table">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Date</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Fulfillment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_orders)): ?>
                                <tr><td colspan="7">No recent orders.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td>#<?php echo $order['id']; ?></td>
                                        <td><?php echo htmlspecialchars($order['username']); ?></td>
                                        <td><?php echo date('F j, Y', strtotime($order['created_at'])); ?></td>
                                        <td>&#8358;<?php echo number_format($order['total'], 2); ?></td>
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
                                            <span class="badge orders-badge bg-<?php
                                                $fulfillment = (isset($order['fulfillment_status']) && $order['fulfillment_status']) ? $order['fulfillment_status'] : '';
                                                switch ($fulfillment) {
                                                    case 'pending': echo 'warning'; break;
                                                    case 'fulfilled': echo 'success'; break;
                                                    case 'failed': echo 'danger'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>">
                                                <?php echo $fulfillment ? ucfirst($fulfillment) : 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            <?php if ($order['fulfillment_status'] === 'pending' && $order['supplier_order_id']): ?>
                                                <a href="fulfill_order.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-success">Fulfill</a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- Low Stock Products -->
        <div class="card shadow-sm admin-card mb-4">
            <div class="card-body">
                <h4 class="card-title" style="color: var(--temu-primary);">Low Stock Products</h4>
                <div class="table-responsive">
                    <table class="table table-hover orders-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Stock</th>
                                <th>Price</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($low_stock_products)): ?>
                                <tr><td colspan="4">No low stock products.</td></tr>
                            <?php else: ?>
                                <?php foreach ($low_stock_products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                                        <td><?php echo $product['stock']; ?></td>
                                        <td>&#8358;<?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>

    <script>
        const ctx = document.getElementById('dropshipChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Total Orders', 'Dropship Orders', 'Total Revenue ($)', 'Total Users', 'Total Products', 'Active Suppliers'],
                datasets: [{
                    label: 'Metrics',
                    data: [<?php echo $total_orders; ?>, <?php echo $dropship_orders; ?>, <?php echo $total_revenue; ?>, <?php echo $total_users; ?>, <?php echo $total_products; ?>, <?php echo $active_suppliers; ?>],
                    backgroundColor: ['#f85606', '#ff7e3e', '#6f42c1', '#007bff', '#28a745', '#ffc107'],
                    borderColor: ['#d94a05', '#e66b2e', '#5a2d8c', '#0056b3', '#1e7e34', '#e6a900'],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Value' } },
                    x: { title: { display: true, text: 'Metrics' } }
                },
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Dropshipping Dashboard Metrics',
                        font: { size: 16, weight: 'bold' },
                        color: '#333333'
                    }
                },
                maintainAspectRatio: false,
                responsive: true
            }
        });
    </script>
</body>
</html>