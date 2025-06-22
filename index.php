<?php
require __DIR__ . '/includes/config.php';

function getImageOrDefault($img, $type = 'product') {
    $img = trim($img ?? '');
    $path = __DIR__ . '/assets/images/' . $img;
    if ($img && file_exists($path)) {
        return htmlspecialchars($img);
    }
    return '';
}

if (!defined('PHP_SESSION_NONE')) {
    define('PHP_SESSION_NONE', 1); // 1 = session not started
}

if (!function_exists('session_status')) {
    function session_status() {
        return session_id() === '' ? PHP_SESSION_NONE : PHP_SESSION_ACTIVE;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Fetch featured products (e.g., latest or high-stock products)
$stmt = $pdo->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.stock > 0 ORDER BY p.created_at DESC LIMIT 6");
$featured_products = $stmt->fetchAll();

// Fetch categories
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name LIMIT 8");
$categories = $stmt->fetchAll();
require __DIR__ . '/includes/header.php';
?>

 <link href="assets/css/styles.css" rel="stylesheet">
<style></style>
    <div class="hero">
        <div class="container">
            <h1>Welcome to E-Shop</h1>
            <p>Discover the best products at unbeatable prices!</p>
            <a href="products.php" class="btn btn-primary btn-lg">Shop Now</a>
        </div>
    </div>
 <!-- Carousel -->
<div id="promoCarousel" class="carousel slide mb-5" data-bs-ride="carousel">
    <div class="carousel-inner">
        <div class="carousel-item active">
            <img src="assets/images/promo1.jpeg" class="d-block w-100 lazy" data-src="assets/images/promo1.jpg" alt="Promo 1">
            <div class="carousel-caption d-none d-md-block">
                <h3>Big Sale!</h3>
                <p>Up to 50% off select items!</p>
            </div>
        </div>
        <div class="carousel-item">
            <img src="assets/images/promo2.jpeg" class="d-block w-100 lazy" data-src="assets/images/promo2.jpg" alt="Promo 2">
            <div class="carousel-caption d-none d-md-block">
                <h3>Free Shipping</h3>
                <p>On orders over $50!</p>
            </div>
        </div>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#promoCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#promoCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
</div>
<!-- Products -->
<div class="container mt-5">
    <h2> Products</h2>
    <div class="row">
        <?php if (empty($featured_products)): ?>
            <p>No products available.</p>
        <?php else: ?>
            <?php foreach ($featured_products as $product): ?>
                <div class="col-md-4 mb-4">
                    <div class="card h-100 position-relative">
                        <?php
                        // Check for active discount
                        $stmt = $pdo->prepare("SELECT discount_percentage FROM discount_codes WHERE product_id = ? AND valid_from <= NOW() AND valid_until >= NOW() AND (max_uses = 0 OR uses < max_uses) LIMIT 1");
                        $stmt->execute([$product['id']]);
                        $discount = $stmt->fetchColumn();
                        if ($discount):
                        ?>
                            <span class="discount-badge"><?php echo $discount; ?>% OFF</span>
                        <?php endif; ?>
                        <img src="assets/images/<?php echo getImageOrDefault($product['image'], 'product'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text"><?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?></p>
                            <p class="card-text"><strong>&#8358;<?php echo number_format($product['price'], 2); ?></strong></p>
                            <div class="d-flex gap-2">
                                <a href="view_products.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">View</a>
                                <button class="btn btn-primary quick-add-to-cart" data-product-id="<?php echo $product['id']; ?>">Add to Cart</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
    <!-- Featured Products -->
    <div class="container mt-5">
        <h2>Featured Products</h2>
        <div class="row">
            <?php if (empty($featured_products)): ?>
                <p>No products available.</p>
            <?php else: ?>
                <?php foreach ($featured_products as $product): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card h-100">
                            <img src="assets/images/<?php echo getImageOrDefault($product['image'], 'product'); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text"><?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?></p>
                                <p class="card-text"><strong>$<?php echo number_format($product['price'], 2); ?></strong></p>
                                <a href="view_products.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">View Product</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Categories -->
    <div class="container mt-5">
        <h2>Shop by Category</h2>
        <div class="row">
            <?php if (empty($categories)): ?>
                <p>No categories available.</p>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <div class="col-md-3 mb-4">
                        <div class="card category-card">
                            <img src="<?php echo $baseUrl; ?>assets/images/<?php echo htmlspecialchars($category['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($category['name']); ?>">
                            <div class="card-body text-center">
                                <h5 class="card-title"><?php echo htmlspecialchars($category['name']); ?></h5>
                                <a href="products.php?category_id=<?php echo $category['id']; ?>" class="btn btn-outline-primary">Browse</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <!-- Promotional Banner -->
    <div class=" text-white text-center py-4 mt-5 " style="background-color: #f85606;">
        <div class="container">
            <h3>Special Offer!</h3>
            <p>Use code <strong>SAVE10</strong> for 10% off your first order!</p>
            <a href="products.php" class="btn btn-light">Shop Now</a>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>