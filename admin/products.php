<?php
require '../includes/config.php';
 require_once '../vendor/autoload.php'; // Adjust path if needed
            use PHPMailer\PHPMailer\PHPMailer;
            use PHPMailer\PHPMailer\Exception;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Restrict to admins
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php");
    exit;
}

$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $category_id = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT) ?: null;
    $stock = filter_input(INPUT_POST, 'stock', FILTER_VALIDATE_INT);
    $image = $_FILES['image']['name'];

    // Validate inputs
    if (!$name || $price === false || $price < 0 || $stock === false || $stock < 0) {
        $error = "Please fill in all required fields correctly.";
    } elseif ($image) {
        // Validate image
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $max_size = 2 * 1024 * 1024; // 2MB
        if (!in_array($_FILES['image']['type'], $allowed_types) || $_FILES['image']['size'] > $max_size) {
            $error = "Invalid image file. Use JPEG/PNG/GIF, max 2MB.";
        } else {
            $image_path = "../assets/images/" . basename($image);
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $image_path)) {
                $error = "Failed to upload image.";
            }
        }
    }

    if (!$error) {
        try {
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, category_id, stock, image) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $description ?: null, $price, $category_id, $stock, $image ?: null]);
            $success = "Product added successfully.";

            // Email notification to all users using PHPMailer SMTP
           
            $userStmt = $pdo->query("SELECT email FROM users WHERE email IS NOT NULL AND email != ''");
            $userEmails = $userStmt->fetchAll(PDO::FETCH_COLUMN);

            if ($userEmails) {
                $subject = "New Product Added: " . $name;
                $message = '
                <html>
                <head>
                  <style>
                    .email-container {
                      font-family: Arial, sans-serif;
                      background: #f8f9fa;
                      padding: 24px;
                      border-radius: 8px;
                      color: #222;
                    }
                    .product-title {
                      color: #f85606;
                      font-size: 1.2rem;
                      font-weight: bold;
                    }
                    .product-desc {
                      margin: 12px 0;
                    }
                    .product-price {
                      color: #388e3c;
                      font-size: 1.1rem;
                      font-weight: bold;
                    }
                    .shop-btn {
                      display: inline-block;
                      margin-top: 18px;
                      padding: 10px 22px;
                      background: #f85606;
                      color: #fff !important;
                      border-radius: 5px;
                      text-decoration: none;
                      font-weight: 500;
                    }
                  </style>
                </head>
                <body>
                  <div class="email-container">
                    <p>Hello,</p>
                    <p>A new product has been added to our store:</p>
                    <div class="product-title">' . htmlspecialchars($name) . '</div>
                    <div class="product-desc">' . nl2br(htmlspecialchars($description)) . '</div>
                    <div class="product-price">Price: ₦' . number_format($price, 2) . '</div>
                    <a class="shop-btn" href="' . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . '/products.php?product_id=' . $pdo->lastInsertId() : '#') . '">View Product</a>
                    <p style="margin-top:24px;">Best regards,<br>E-Shop Team</p>
                  </div>
                </body>
                </html>
                ';

                // SMTP config
                $mail = new PHPMailer(true);
                try {
                     $mail->isSMTP();
                        $mail->Host = SMTP_HOST;
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                    foreach ($userEmails as $email) {
                        $mail->addAddress($email);
                    }
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body = $message;

                    $mail->send();
                } catch (Exception $e) {
                    error_log("PHPMailer error: " . $mail->ErrorInfo);
                }
            }
        } catch (PDOException $e) {
            error_log("Product insertion error: " . $e->getMessage());
            $error = "An error occurred. Please try again.";
        }
    }
}

// Fetch products with category names
$stmt = $pdo->query("SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id");
$products = $stmt->fetchAll();
?>


    <?php include '../includes/header.php'; ?>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <div class="container mt-5 mb-5 admin-container">
        <h2 class="mb-4" style="color: var(--temu-primary);">Manage Products</h2>
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
        <div class="card shadow-sm admin-card">
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="add-product-form">
                    <div class="mb-3">
                        <label for="name" class="form-label">Product Name</label>
                        <input type="text" name="name" id="name" class="form-control" required>
                        <div class="invalid-feedback">Product name is required.</div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea name="description" id="description" class="form-control"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="price" class="form-label">Price</label>
                        <input type="number" name="price" id="price" class="form-control" step="0.01" min="0" required>
                        <div class="invalid-feedback">Please enter a valid price.</div>
                    </div>
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select name="category_id" id="category_id" class="form-select">
                            <option value="">Uncategorized</option>
                            <?php
                            $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY name");
                            foreach ($stmt->fetchAll() as $category) {
                                echo "<option value='{$category['id']}'>" . htmlspecialchars($category['name']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="stock" class="form-label">Stock</label>
                        <input type="number" name="stock" id="stock" class="form-control" min="0" required>
                        <div class="invalid-feedback">Please enter a valid stock quantity.</div>
                    </div>
                    <div class="mb-3">
                        <label for="image" class="form-label">Image</label>
                        <input type="file" name="image" id="image" class="form-control" accept="image/jpeg,image/png,image/gif">
                        <div class="invalid-feedback">Please upload a valid image file.</div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Add Product</button>
                </form>
            </div>
        </div>
        <div class="card shadow-sm admin-card mt-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Category</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <tr>
                                    <td><?php echo $product['id']; ?></td>
                                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                                    <td>&#8358;<?php echo number_format($product['price'], 2); ?></td>
                                    <td class="<?php echo $product['stock'] < 10 ? 'low-stock' : ''; ?>">
                                        <?php echo $product['stock']; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Uncategorized'); ?></td>
                                    <td>
                                        <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                        <a href="delete_product.php?id=<?php echo $product['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this product?')">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/scripts.js"></script>
</body>
</html>