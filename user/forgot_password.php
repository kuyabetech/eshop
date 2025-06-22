<?php
require '../includes/config.php';
require '../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

    // Redis rate limiting (after $email is set)
    if (class_exists('Redis')) {
        $cacheKey = "reset_attempts:$email";
        $redis = new Redis();
        try {
            $redis->connect('localhost', 6379);
            $attempts = $redis->get($cacheKey) ?: 0;
            if ($attempts >= 5) {
                $error = "Too many reset attempts. Try again in 1 hour.";
            } else {
                $redis->setex($cacheKey, 3600, $attempts + 1);
                // Proceed with reset logic
            }
        } catch (Exception $e) {
            error_log('Redis connection failed: ' . $e->getMessage());
            // Proceed without Redis rate limiting
        }
    } // If Redis is not available, just proceed without rate limiting

    // Only proceed if no error from rate limiting
    if (!isset($error)) {
        // Validate CSRF token
        if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
            $error = "CSRF token validation failed.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address.";
        } else {
            try {
                // Check if email exists
                $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user) {
                    // Generate reset token
                    $token = bin2hex(random_bytes(32));
                    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, created_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE token = ?, created_at = NOW()");
                    $stmt->execute([$email, $token, $token]);

                    // Send reset email
                    $mail = new PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = SMTP_HOST; // Define in config.php
                        $mail->SMTPAuth = true;
                        $mail->Username = SMTP_USERNAME;
                        $mail->Password = SMTP_PASSWORD;
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;
                        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                        $mail->addAddress($email, $user['username']);
                        $mail->isHTML(true);
                        $mail->Subject = "Reset Your E-Shop Password";
                        $mail->Body = "<div style='font-family:Poppins,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f8f9fa;padding:24px;'>"
                        . "<h1 style='color:#f85606;margin-bottom:16px;'>E-Shop Password Reset</h1>"
    . "<div style='background:#fff;border-radius:12px;padding:24px;max-width:500px;margin:auto;border:1px solid #eee;'>"
    . "<h2 style='color:#f85606;margin-bottom:16px;'>Password Reset Request</h2>"
    . "<p style='font-size:16px;color:#333333;'>Dear <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>"
    . "<p style='font-size:15px;color:#333333;'>We received a request to reset your E-Shop account password.</p>"
    . "<div style='margin:24px 0;text-align:center;'><a href='" . BASE_URL . "user/reset_password.php?token=$token' style='background:#f85606;color:#fff;padding:10px 24px;border-radius:12px;text-decoration:none;font-weight:500;cursor:pointer;'>Reset Password</a></div>"
    . "<p style='font-size:15px;color:#333333;'>If you did not request this, you can safely ignore this email. This link will expire in 1 hour.</p>"
    . "<hr style='margin:24px 0;border-color:#f8f9fa;'>"
    . "<p style='font-size:13px;color:#888;'>For help, contact <a href='mailto:support@eshop.com' style='color:#007bff;'>support@eshop.com</a>.<br>Thank you for using E-Shop!<br>E-Shop Team</p>"
    . "</div>"
    . "</div>";
                        $mail->send();
                        $success = "A password reset link has been sent to your email.";
                    } catch (Exception $e) {
                        error_log("Email sending failed: " . $mail->ErrorInfo);
                        $error = "Failed to send reset link. Please try again.";
                    }
                } else {
                    $error = "No account found with that email.";
                }
            } catch (PDOException $e) {
                error_log("Forgot password error: " . $e->getMessage());
                $error = "An error occurred. Please try again.";
            }
        }
    }
}

// Generate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - E-Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">
    <link href="../assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <div class="container mt-5 mb-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card p-4 shadow-sm login-card">
                    <h2 class="text-center mb-4" style="color: var(--temu-primary);">Forgot Password</h2>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php elseif (isset($success)): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($success); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="" id="forgot-password-form" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <a href="login.php" class="text-decoration-none" style="color: var(--temu-blue);">Back to Login</a>
                            <a href="register.php" class="text-decoration-none" style="color: var(--temu-blue);">Register</a>
                        </div>
                        <!-- Add to form -->
<div class="g-recaptcha"
      data-sitekey="6LeDWmgrAAAAAMlKpqeXs87ZKXo_dU47dtQdbKoZ"
      data-callback="onSubmit"
      data-size="invisible">
</div>
                        <button type="submit" class="btn btn-primary w-100">Send Reset Link</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://www.google.com/recaptcha/api.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.js"></script>
    <script src="../assets/js/scripts.js"></script>
    <script>
      // Forgot Password Form Validation
$('#forgot-password-form').on('submit', function(e) {
    const form = this;
    if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        $(form).addClass('was-validated');
        return false;
    }

    const email = $('#email').val();
    if (!email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
        e.preventDefault();
        $('#email').addClass('is-invalid');
        $('#email').next('.invalid-feedback').text('Please enter a valid email address.');
        return false;
    }
    $(this).find('button[type="submit"]').prop('disabled', true).text('Sending...');
});

// Reset Password Form Validation
$('#reset-password-form').on('submit', function(e) {
    const form = this;
    if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        $(form).addClass('was-validated');
        return false;
    }

    const password = $('#password').val();
    const confirmPassword = $('#confirm_password').val();

    if (password.length < 8) {
        e.preventDefault();
        $('#password').addClass('is-invalid');
        $('#password').next('.invalid-feedback').text('Password must be at least 8 characters.');
        return false;
    }

    if (password !== confirmPassword) {
        e.preventDefault();
        $('#confirm_password').addClass('is-invalid');
        $('#confirm_password').next('.invalid-feedback').text('Passwords do not match.');
        return false;
    }
    $(this).find('button[type="submit"]').prop('disabled', true).text('Resetting...');
});
grecaptcha.execute();

// Clear Validation on Input (already covers email, password, confirm_password)
    </script>
</body>
</html>