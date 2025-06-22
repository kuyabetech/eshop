<?php
require 'includes/config.php';

// Safely retrieve GET inputs
$search = filter_input(INPUT_GET, 'search', FILTER_DEFAULT);
$category = filter_input(INPUT_GET, 'category', FILTER_DEFAULT);
$min_price = filter_input(INPUT_GET, 'min_price', FILTER_VALIDATE_FLOAT);
$max_price = filter_input(INPUT_GET, 'max_price', FILTER_VALIDATE_FLOAT);

// Basic sanitization (modern practice)
$search = htmlspecialchars(trim($search ?? ''));
$category = htmlspecialchars(trim($category ?? ''));

// Start building query
$query = "SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND p.name LIKE ?";
    $params[] = "%$search%";
}

if (!empty($category)) {
    $query .= " AND c.name = ?";
    $params[] = $category;
}

if ($min_price !== false && $min_price !== null) {
    $query .= " AND p.price >= ?";
    $params[] = $min_price;
}

if ($max_price !== false && $max_price !== null) {
    $query .= " AND p.price <= ?";
    $params[] = $max_price;
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll();
?>

    <?php include 'includes/header.php'; ?>
    <?php
if (isset($_GET['success'])) {
    echo "<div class='alert alert-success'>" . htmlspecialchars($_GET['success']) . "</div>";
}
if (isset($_GET['error'])) {
    echo "<div class='alert alert-danger'>" . htmlspecialchars($_GET['error']) . "</div>";
}
?>
    <div class="container mt-5">
        <h2>Products</h2>
        <form method="GET" class="mb-4">
            <div class="row">
                <div class="col-md-3">
<input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="category" class="form-control">
                        <option value="">All Categories</option>
                        <option value="electronics" <?php if ($category == 'electronics') echo 'selected'; ?>>Electronics</option>
                        <option value="clothing" <?php if ($category == 'clothing') echo 'selected'; ?>>Clothing</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="number" name="min_price" class="form-control" placeholder="Min Price" value="<?php echo $min_price ?: ''; ?>">
                </div>
                <div class="col-md-2">
                    <input type="number" name="max_price" class="form-control" placeholder="Max Price" value="<?php echo $max_price ?: ''; ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Filter</button>
                </div>
            </div>
        </form>
        <div class="row">
            <?php foreach ($products as $product): ?>
                <div class="col-md-4 mb-4">
                    <div class="card">
                        <img src="assets/images/<?php echo htmlspecialchars($product['image']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text">&#8358;<?php echo number_format($product['price'], 2); ?></p>
                            <a href="view_products.php?id=<?php echo $product['id']; ?>" class="btn btn-primary">View</a>
                            <a href="cart/add_to_cart.php?id=<?php echo $product['id']; ?>" class="btn btn-success">Add to Cart</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>
</body>
</html>