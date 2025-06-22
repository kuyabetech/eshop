<?php
require __DIR__ . '/includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get product ID
$product_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$product_id) {
    header("Location: ../index.php?error=Invalid product ID");
    exit;
}

// Fetch product
$stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$product_id]);
$product = $stmt->fetch();

if (!$product) {
    header("Location: ../index.php?error=Product not found");
    exit;
}

// Handle review submission
$success = $error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5]]);
    $comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed.";
    } elseif (!$rating) {
        $error = "Please select a valid rating.";
    } else {
        try {
            // Check if user already reviewed this product
            $stmt = $pdo->prepare("SELECT id FROM reviews WHERE product_id = ? AND user_id = ?");
            $stmt->execute([$product_id, $_SESSION['user_id']]);
            if ($stmt->fetch()) {
                $error = "You have already reviewed this product.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, approved) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, $_SESSION['user_id'], $rating, $comment, false]);
                $success = "Review submitted successfully. It will appear after admin approval.";
            }
        } catch (PDOException $e) {
            error_log("Review submission error: " . $e->getMessage());
            $error = "Failed to submit review.";
        }
    }
}

// Fetch approved reviews (and user's pending review if logged in)
$sql = "SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.product_id = ? AND r.approved = 1";
$params = [$product_id];
if (isset($_SESSION['user_id'])) {
    $sql .= " OR (r.product_id = ? AND r.user_id = ?)";
    $params[] = $product_id;
    $params[] = $_SESSION['user_id'];
}
$sql .= " ORDER BY r.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reviews = $stmt->fetchAll();

// Generate CSRF token if not set
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($product['name']); ?> - E-Shop</title>
    <link href="[invalid url, do not cite] rel="stylesheet">
    <link href="[invalid url, do not cite] rel="stylesheet">
    <link rel="stylesheet" href="assets/css/custom.css">
</head>
<body>
    <?php require __DIR__ . '/includes/header.php'; ?>
    <div class="container mt-5 mb-5 product-container">
        <div class="row">
            <div class="col-md-6">
            <img src="assets/images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-fluid" style="width: 100%;">
            </div>
            <div class="col-md-6">
                <h2 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h2>
                <p class="product-description"><?php echo htmlspecialchars($product['description']); ?></p>
                <p class="product-price"><strong>Price:</strong> $<?php echo number_format($product['price'], 2); ?></p>
                <p class="product-stock"><strong>Stock:</strong> <?php echo $product['stock']; ?></p>
                <form action="cart/add_to_cart.php" method="GET" class="add-to-cart-form">
                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                    <div class="input-group mb-3">
                        <input type="number" name="quantity" value="1" min="1" max="<?php echo $product['stock']; ?>" class="form-control quantity-input">
                        <button type="submit" class="btn btn-primary">Add to Cart</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Review Form -->
        <div class="review-section mt-5">
            <h4 class="review-title">Submit a Review</h4>
            <?php if (!isset($_SESSION['user_id'])): ?>
                <p>Please <a href="../user/login.php" class="text-decoration-none" style="color: var(--temu-blue);">log in</a> to submit a review.</p>
            <?php else: ?>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <form method="POST" action="" class="review-form">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-3">
                        <label for="rating" class="form-label">Rating (1-5)</label>
                        <div class="star-rating">
                            <span class="star" data-value="1">★</span>
                            <span class="star" data-value="2">★</span>
                            <span class="star" data-value="3">★</span>
                            <span class="star" data-value="4">★</span>
                            <span class="star" data-value="5">★</span>
                        </div>
                        <select name="rating" id="rating" class="form-control d-none" required>
                            <option value="">Select rating</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?php echo $i; ?>"><?php echo $i; ?> Star<?php echo $i > 1 ? 's' : ''; ?></option>
                            <?php endfor; ?>
                        </select>
                        <div class="invalid-feedback">Please select a rating.</div>
                    </div>
                    <div class="mb-3">
                        <label for="comment" class="form-label">Comment</label>
                        <textarea name="comment" id="comment" class="form-control" rows="4"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Review</button>
                </form>
            <?php endif; ?>
        </div>

        <!-- Display Reviews -->
        <div class="reviews-section mt-5">
            <h4 class="reviews-title">Product Reviews</h4>
            <?php if (empty($reviews)): ?>
                <p class="no-reviews">No reviews yet.</p>
            <?php else: ?>
                <?php foreach ($reviews as $review): ?>
                    <div class="card review-card mb-3">
                        <div class="card-body">
                            <h5 class="card-title review-author"><?php echo htmlspecialchars($review['username']); ?> - <?php echo $review['rating']; ?> Star<?php echo $review['rating'] > 1 ? 's' : ''; ?></h5>
                            <p class="card-text review-comment"><?php echo htmlspecialchars($review['comment'] ?: 'No comment'); ?></p>
                            <p class="card-text review-meta">
                                <small class="text-muted">
                                    <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                                    <?php if (!$review['approved'] && $review['user_id'] == ($_SESSION['user_id'] ?? 0)): ?>
                                        <span class="pending-review">(Pending Approval)</span>
                                    <?php endif; ?>
                                </small>
                            </p>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php require __DIR__ . '/includes/footer.php'; ?>
    <script src="[invalid url, do not cite]
    <script src="[invalid url, do not cite]
    <script src="../assets/js/scripts.js"></script>
</body>
</html>