<?php
require '../includes/config.php';

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

// Fetch dashboard metrics
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

    // Recent orders (last 5)
    $stmt = $pdo->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll();

    // Low stock products (stock <= 10)
    $stmt = $pdo->query("SELECT * FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 5");
    $low_stock_products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $error = "Failed to load dashboard data. Please try again.";
}
?>

    <?php include '../includes/header.php'; ?>
    <link href="../assets/css/styles.css" rel="stylesheet">  
    <div class="container mt-5">
        <h2>Admin Dashboard</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <!-- Metrics -->
        <div class="row mb-5">
            <div class="col-md-3 mb-3">
                <div class="metric-card">
                    <h3><?php echo $total_orders; ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="metric-card">
                    <h3><span class="naira-icon">&#8358;</span><?php echo number_format($total_revenue, 2); ?></h3>
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

        <!-- Chart Section -->
         <!-- Chart Section -->
        <div class="card shadow-sm admin-card mb-4">
            <div class="card-body">
                <canvas id="dashboardChart" height="400"></canvas>
            </div>
        </div>
        
        <!-- Recent Orders -->
        <div class="row mb-5">
            <div class="col-md-12">
                <h4>Recent Orders</h4>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>User</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                            <tr><td colspan="6">No recent orders.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <tr>
                                    <td>#<?php echo $order['id']; ?></td>
                                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                                    <td><?php echo date('F j, Y', strtotime($order['created_at'])); ?></td>
                                    <td>$<?php echo number_format($order['total'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php
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
                                        <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- Low Stock Products -->
        <div class="row">
            <div class="col-md-12">
                <h4>Low Stock Products</h4>
                <table class="admin-table table-striped">
                    <thead>
                        <tr>
                            <th>Product ID</th>
                            <th>Name</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($low_stock_products)): ?>
                            <tr><td colspan="4">No low stock products.</td></tr>
                        <?php else: ?>
                            <?php foreach ($low_stock_products as $product): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td><?php echo $product['stock']; ?></td>
                                    <td>
                                        <a href="inventory.php" class="btn btn-sm btn-warning">Update Stock</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.min.js" integrity="sha512-L0Shl7nXXzIlBSUUPpxrokqq4ojqgZFQczTYlGjzONGTDAcLremjwaWv5A+EDLnxhQzY5xUZPWLOLqYRkY0Cbw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
        <script>
        const ctx = document.getElementById('dashboardChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Total Orders', 'Total Revenue ($)', 'Total Users', 'Total Products'],
                datasets: [{
                    label: 'Metrics',
                    data: [<?php echo $total_orders; ?>, <?php echo $total_revenue; ?>, <?php echo $total_users; ?>, <?php echo $total_products; ?>],
                    backgroundColor: ['#f85606', '#6f42c1', '#007bff', '#28a745'],
                    borderColor: ['#d94a05', '#5a2d8c', '#0056b3', '#1e7e34'],
                    borderWidth: 1
                }]
            },
            options: {
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Value'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Metrics'
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Admin Dashboard Metrics',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
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