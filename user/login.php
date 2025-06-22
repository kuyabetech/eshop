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
    $password = $_POST['password'];
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

    // Validate CSRF token
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Send login notification email
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
                $mail->Subject = "Login Notification - E-Shop";
                $login_time = date('Y-m-d H:i:s');
                $user_agent = htmlspecialchars($_SERVER['HTTP_USER_AGENT']);
                $ip_address = $_SERVER['REMOTE_ADDR'];
                $mail->Body = "<div style='font-family:Poppins,-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;background:#f8f9fa;padding:24px;'>"
                    . "<div style='background:#fff;border-radius:12px;padding:24px;max-width:500px;margin:auto;border:1px solid #eee;'>"
                    . "<h2 style='color:#f85606;margin-bottom:16px;'>Login Detected</h2>"
                    . "<p style='font-size:16px;color:#333333;'>Dear <strong>" . htmlspecialchars($user['username']) . "</strong>,</p>"
                    . "<p style='font-size:15px;color:#333333;'>Your account was logged into on <strong style='color:#6f42c1;'>$login_time</strong>.</p>"
                    . "<p style='font-size:15px;color:#333333;'><strong>Device:</strong> <span style='color:#007bff;'>$user_agent</span></p>"
                    . "<p style='font-size:15px;color:#333333;'><strong>IP Address:</strong> <span style='color:#007bff;'>$ip_address</span></p>"
                    . "<hr style='margin:16px 0;border-color:#f8f9fa;'>"
                    . "<p style='font-size:15px;color:#333333;'>If this was not you, please secure your account by resetting your password <a href='" . BASE_URL . "user/forgot_password.php' style='color:#f85606;text-decoration:underline;'>here</a>.</p>"
                    . "<p style='font-size:15px;color:#333333;'>Contact <a href='mailto:support@yourdomain.com' style='color:#007bff;'>support@yourdomain.com</a> for assistance.</p>"
                    . "<hr style='margin:24px 0;border-color:#f8f9fa;'>"
                    . "<p style='font-size:13px;color:#888;'>Thank you for using E-Shop!<br>E-Shop Team</p>"
                    . "</div>"
                    . "</div>";
                $mail->send();
            } catch (Exception $e) {
                error_log("Login email failed: " . $mail->ErrorInfo);
                // Continue without halting login
            }

            // Role-based redirect
            if ($user['role'] === 'admin') {
                header("Location: ../admin/index.php");
            } else {
                header("Location: ../index.php");
            }
            exit;
        } else {
            $error = "Invalid email or password.";
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
    <title>Login - E-Shop</title>
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
                    <h2 class="text-center mb-4" style="color: var(--temu-primary);">Login</h2>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <form method="POST" action="" id="login-form" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" placeholder="Enter your email" required>
                            <div class="invalid-feedback">Please enter a valid email address.</div>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                                <button type="button" class="btn btn-outline-secondary toggle-password" title="Toggle visibility">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback">Password is required.</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <a href="forgot_password.php" class="text-decoration-none" style="color: var(--temu-blue);">Forgot Password?</a>
                            <a href="register.php" class="text-decoration-none" style="color: var(--temu-blue);">Register</a>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.js"></script>
    <script src="assets/js/scripts.js"></script>
    <script>
        $('#login-form').on('submit', function(e) {
    const form = this;
    if (!form.checkValidity()) {
        e.preventDefault();
        e.stopPropagation();
        $(form).addClass('was-validated');
        return false;
    }
    $(this).find('button[type="submit"]').prop('disabled', true).text('Logging in...');
});
        $('.toggle-password').on('click', function() {
            const input = $(this).siblings('input');
            const type = input.attr('type') === 'password' ? 'text' : 'password';
            input.attr('type', type);
            $(this).find('i').toggleClass('fa-eye fa-eye-slash');
        });
        $(document).ready(function() {
            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();

            // Form validation
            $('#login-form').on('submit', function(e) {
                const form = this;
                if (!form.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(form).addClass('was-validated');
                }
            });

            // Toggle password visibility
            $('.toggle-password').on('click', function() {
                const input = $(this).siblings('input');
                const type = input.attr('type') === 'password' ? 'text' : 'password';
                input.attr('type', type);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });
        });
    </script>
</body>
</html>