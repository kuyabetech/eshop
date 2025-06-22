<?php
require '../includes/config.php';

// Get search query
$query = filter_input(INPUT_GET, 'q', FILTER_SANITIZE_STRING);
$products = [];

if ($query) {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE name LIKE ? AND stock > 0");
    $stmt->execute(["%$query%"]);
    $products = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Search Results for "<?php echo htmlspecialchars($query); ?>"</h2>
        <?php if (empty($products)): ?>
            <p>No products found.</p>
        <?php else: ?>
            <div class="row">
                <?php foreach ($products as $product): ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <img src="../assets/images/<?php echo htmlspecialchars($product['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="card-text">$<?php echo number_format($product['price'], 2); ?></p>
                                <a href="view_product.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">View Product</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>