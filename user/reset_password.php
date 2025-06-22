<?php
require '../includes/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$token = filter_input(INPUT_GET, 'token', FILTER_SANITIZE_STRING);
$valid_token = false;
$email = null;

if ($token) {
    try {
        // Check token validity (expires after 1 hour)
        $stmt = $pdo->prepare("SELECT email FROM password_resets WHERE token = ? AND created_at >= NOW() - INTERVAL 1 HOUR");
        $stmt->execute([$token]);
        $email = $stmt->fetchColumn();
        if ($email) {
            $valid_token = true;
        } else {
            $error = "Invalid or expired reset token.";
        }
    } catch (PDOException $e) {
        error_log("Token validation error: " . $e->getMessage());
        $error = "An error occurred. Please try again.";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $csrf_token = filter_input(INPUT_POST, 'csrf_token', FILTER_SANITIZE_STRING);

    // Validate CSRF token
    if (!$csrf_token || $csrf_token !== $_SESSION['csrf_token']) {
        $error = "CSRF token validation failed.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Update password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);

            // Delete used token
            $stmt = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
            $stmt->execute([$email]);

            $success = "Password reset successfully. Please log in.";
            // Redirect to login after a short delay
            header("Refresh: 3; url=login.php");
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error = "An error occurred. Please try again.";
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
    <title>Reset Password - E-Shop</title>
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
                    <h2 class="text-center mb-4" style="color: var(--temu-primary);">Reset Password</h2>
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
                    <?php elseif (!$valid_token): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            Invalid or expired reset token. Please request a new link.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    <?php if ($valid_token): ?>
                        <form method="POST" action="" id="reset-password-form" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter new password" required>
                                    <button type="button" class="btn btn-outline-secondary toggle-password" title="Toggle visibility">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Password must be at least 8 characters.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <div class="input-group">
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password" required>
                                    <button type="button" class="btn btn-outline-secondary toggle-password" title="Toggle visibility">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback">Passwords do not match.</div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <a href="login.php" class="text-decoration-none" style="color: var(--temu-blue);">Back to Login</a>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                        </form>
                    <?php else: ?>
                        <a href="forgot_password.php" class="btn btn-primary w-100">Request New Link</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php include '../includes/footer.php'; ?>
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

// Clear Validation on Input (already covers email, password, confirm_password)
$('#password').on('input', function() {
    const password = $(this).val();
    const strength = password.length >= 12 ? 'Strong' : password.length >= 8 ? 'Medium' : 'Weak';
    const feedback = $(this).next('.invalid-feedback');
    feedback.text(`Password strength: ${strength}`);
    feedback.removeClass('invalid-feedback').addClass('form-text');
    if (strength === 'Weak') {
        $(this).addClass('is-invalid');
        feedback.addClass('invalid-feedback').removeClass('form-text');
    } else {
        $(this).removeClass('is-invalid');
    }
});
    </script>
</body>
</html>