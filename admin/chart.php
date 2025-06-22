<?php
require '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

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

    // Recent orders and low stock (as before)
    $stmt = $pdo->query("SELECT o.*, u.username FROM orders o JOIN users u ON o.user_id = u.id ORDER BY o.created_at DESC LIMIT 5");
    $recent_orders = $stmt->fetchAll();

    $stmt = $pdo->query("SELECT * FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 5");
    $low_stock_products = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $error = "Failed to load dashboard data. Please try again.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="[invalid url, do not cite] rel="stylesheet">
    <link href="[invalid url, do not cite] rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="[invalid url, do not cite]</script>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5 mb-5 admin-container">
        <h2 class="mb-4" style="color: var(--temu-primary);">Admin Dashboard</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <!-- Chart Section -->
        <div class="card shadow-sm admin-card mb-4">
            <div class="card-body">
                <canvas id="dashboardChart" height="400"></canvas>
            </div>
        </div>
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
                    <h3>$<?php echo number_format($total_revenue, 2); ?></h3>
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
        <!-- Quick Actions and Recent Orders/Low Stock as before -->
        <!-- ... -->
    </div>
    <?php include '../includes/footer.php'; ?>
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