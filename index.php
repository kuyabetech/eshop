<?php
require_once 'includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    // Fetch carousel products (top 5 by stock or recent)
    $stmt = $pdo->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.stock > 0 ORDER BY p.created_at DESC LIMIT 5");
    $carousel_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch grid products (next 8 by stock or recent)
    $stmt = $pdo->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.stock > 0 ORDER BY p.created_at DESC LIMIT 8 OFFSET 5");
    $grid_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch categories
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Index query error: " . $e->getMessage());
    $error = "Failed to load products.";
}

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Shop - Home</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="container mt-5 mb-5">
        <h1 class="mb-4 admin-title" style="color: var(--temu-primary);font-size: 2rem; text-align: center;">Welcome to E-Shop</h1>
        <!-- Carousel -->
        <?php if (!empty($carousel_products)): ?>
            <div id="featuredCarousel" class="carousel slide mb-5" data-bs-ride="carousel" aria-label="Featured Products Carousel">
                <div class="carousel-indicators">
                    <?php foreach ($carousel_products as $index => $product): ?>
                        <button type="button" data-bs-target="#featuredCarousel" data-bs-slide-to="<?php echo $index; ?>" <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?> aria-label="Slide <?php echo $index + 1; ?>"></button>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-inner">
                    <?php foreach ($carousel_products as $index => $product): ?>
                        <?php
                        // Ensure image path is correct
                        $img = trim($product['image'] ?? '');
                        if ($img && strpos($img, 'assets/images/') !== 0) {
                            $img = 'assets/images/' . $img;
                        }
                        if (!$img) {
                            $img = 'assets/images/placeholder.jpg';
                        }

                        // Fetch average rating for the product
                        $rating = 0;
                        $rating_count = 0;
                        try {
                            $ratingStmt = $pdo->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as count_rating FROM reviews WHERE product_id = ?");
                            $ratingStmt->execute([$product['id']]);
                            $ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC);
                            if ($ratingData) {
                                $rating = round($ratingData['avg_rating'], 1);
                                $rating_count = $ratingData['count_rating'];
                            }
                        } catch (PDOException $e) {
                            $rating = 0;
                            $rating_count = 0;
                        }
                        ?>
                        <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
                            <img src="<?php echo htmlspecialchars($img); ?>" class="d-block w-100" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 400px; object-fit: cover; border-radius: 12px;">
                            <div class="carousel-caption d-none d-md-block" style="background: rgba(0, 0, 0, 0.5); border-radius: 8px; padding: 1rem;">
                                <h3 class="mb-2" style="color: var(--temu-secondary); font-weight: 600;"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <p class="mb-2" style="color: var(--temu-secondary);">$<?php echo number_format($product['price'], 2); ?></p>
                                <!-- Star Rating -->
                                <div class="mb-2">
                                    <?php
                                    $fullStars = floor($rating);
                                    $halfStar = ($rating - $fullStars) >= 0.5;
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $fullStars) {
                                            echo '<span style="color:#FFD700;font-size:1.2em;">&#9733;</span>'; // full star
                                        } elseif ($halfStar && $i == $fullStars + 1) {
                                            echo '<span style="color:#FFD700;font-size:1.2em;">&#9734;</span>'; // half star (use empty star for simplicity)
                                        } else {
                                            echo '<span style="color:#ccc;font-size:1.2em;">&#9733;</span>'; // empty star
                                        }
                                    }
                                    ?>
                                    <span style="color:#fff;font-size:0.95em;">(<?php echo $rating_count; ?>)</span>
                                </div>
                                <a href="products.php?product_id=<?php echo $product['id']; ?>" class="btn btn-primary" style="background: var(--temu-primary); border-color: var(--temu-primary);">Shop Now</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#featuredCarousel" data-bs-slide="prev" aria-label="Previous Slide">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#featuredCarousel" data-bs-slide="next" aria-label="Next Slide">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
            </div>
        <?php endif; ?>
        <!-- Search Bar -->
        <!-- <form method="GET" action="products.php" class="mb-4">
            <div class="input-group">
                <input type="text" class="form-control" name="search" placeholder="Search products..." aria-label="Search products">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form> -->
        <!-- Categories -->
        <div class="mb-4">
            <h4 style="color: var(--temu-primary);">Shop by Category</h4>
            <div class="d-flex flex-wrap">
                <?php foreach ($categories as $category): ?>
                    <a href="products.php?category_id=<?php echo $category['id']; ?>" class="btn btn-outline-primary me-2 mb-2"><?php echo htmlspecialchars($category['name']); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <!-- Featured Products Grid -->
        <h4 class="mb-3" style="color: var(--temu-primary);">More Products</h4>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <div class="row">
            <?php if (empty($grid_products)): ?>
                <p>No products available.</p>
            <?php else: ?>
                <?php foreach ($grid_products as $product): ?>
                    <?php
                    // Ensure image path is correct
                    $img = trim($product['image'] ?? '');
                    if ($img && strpos($img, 'assets/images/') !== 0) {
                        $img = 'assets/images/' . $img;
                    }
                    if (!$img) {
                        $img = 'assets/images/placeholder.jpg';
                    }
                    ?>
                    <div class="col-md-3 mb-4">
                        <div class="card shadow-sm admin-card">
                            <img src="<?php echo htmlspecialchars($img); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>" style="height: 200px; object-fit: cover;">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text text-muted"><?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?></p>
                                <p class="card-text">&#8358;<?php echo number_format($product['price'], 2); ?></p>
                                <form method="POST" action="cart/cart.php" class="needs-validation" novalidate>
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" class="form-control mb-2" style="width: 80px;">
                                    <a href="view_products.php?id=<?php echo $product['id']; ?>" class="btn btn-primary" style="width:100px!important;">View</a>
                                    <a href="cart/add_to_cart.php?id=<?php echo $product['id']; ?>" class="btn btn-success">Add to Cart</a>

                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/scripts.js"></script>
</body>
</html>