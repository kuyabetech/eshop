<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ecommerce');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Stripe API keys
define('STRIPE_PUBLISHABLE_KEY', 'pk_test_...'); // Replace with your test publishable key
define('STRIPE_SECRET_KEY', 'sk_test_...'); // Replace with your test secret key
// Base URL for the site
define('BASE_URL', 'http://localhost/ecommerce/');
// PHPMailer settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'kuyabe3232@gmail.com');
define('SMTP_PASSWORD', 'xfuf tuht hzvu urtq'); // Use an App Password for Gmail
define('SMTP_FROM_EMAIL', 'kuyabe3232@gmail.com');
define('SMTP_FROM_NAME', 'E-Shop');



?>