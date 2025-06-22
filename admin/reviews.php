<?php
require '../includes/config.php';

// Include PHPMailer classes at the top-level scope
require_once '../vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Ensure user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

// Only handle approve if action is set
if (isset($_GET['action']) && $_GET['action'] === 'approve') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($id) {
        $stmt = $pdo->prepare("UPDATE reviews SET approved = 1 WHERE id = ?");
        $stmt->execute([$id]);
        // Fetch user email
        $stmt = $pdo->prepare("SELECT u.email, u.username FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if ($user) {
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
                $mail->addAddress($user['email'], $user['username']);
                $mail->isHTML(true);
                $mail->Subject = "Your Review Has Been Approved";
                $mail->Body = "<div style='font-family:Poppins,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f8f9fa;padding:24px;'>"
                    . "<div style='background:#fff;border-radius:12px;padding:24px;max-width:500px;margin:auto;border:1px solid #eee;'>"
                    . "<h2 style='color:#f85606;margin-bottom:16px;'>Review Approved</h2>"
                    . "<p style='font-size:16px;color:#333333;'>Dear <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>"
                    . "<p style='font-size:15px;color:#333333;'>Your review has been approved and is now visible on our site. Thank you for sharing your feedback!</p>"
                    . "<hr style='margin:24px 0;border-color:#f8f9fa;'>"
                    . "<p style='font-size:13px;color:#888;'>Thank you for being part of E-Shop!<br>E-Shop Team</p>"
                    . "</div>"
                    . "</div>";
                $mail->send();
            } catch (Exception $e) {
                error_log("Email sending failed: " . $mail->ErrorInfo);
            }
        }
    }
}
// Handle review deletion
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete') {
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $csrf_token = filter_input(INPUT_GET, 'csrf_token', FILTER_SANITIZE_STRING);

    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed.";
    } elseif (!$id) {
        $error = "Invalid review ID.";
    } else {
        try {
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$id]);
            $success = "Review deleted successfully.";
        } catch (PDOException $e) {
            $error = "Failed to delete review: " . $e->getMessage();
        }
    }
}

// Fetch all reviews
$stmt = $pdo->query("SELECT r.*, p.name AS product_name, u.username FROM reviews r JOIN products p ON r.product_id = p.id JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");
$reviews = $stmt->fetchAll();
// Remove or protect avg_rating code
// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reviews</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
     <link href="../assets/css/styles.css" rel="stylesheet">  
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5">
        <h2>Manage Reviews</h2>
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>User</th>
                        <th>Rating</th>
                        <th>Comment</th>
                        <th>Date</th>
                        <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reviews as $review): ?>
                    <tr>
                        <td><?php echo $review['id']; ?></td>
                        <td><?php echo htmlspecialchars($review['product_name']); ?></td>
                        <td><?php echo htmlspecialchars($review['username']); ?></td>
                        <td><?php echo $review['rating']; ?> Star<?php echo $review['rating'] > 1 ? 's' : ''; ?></td>
                        <td><?php echo htmlspecialchars($review['comment'] ?: 'No comment'); ?></td>
                        <td><?php echo date('F j, Y', strtotime($review['created_at'])); ?></td>
                        <td>
                            <a href="?action=delete&id=<?php echo $review['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this review?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <a href="products.php" class="btn btn-secondary">Back to Products</a>
    </div>
    <?php include '../includes/footer.php'; ?>
</body>
</html>