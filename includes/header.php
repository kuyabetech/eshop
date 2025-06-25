<?php  
// Start session safely (compatible fallback)  
if (!defined('PHP_SESSION_NONE')) {  
    define('PHP_SESSION_NONE', 1);  
}  
if (!function_exists('session_status')) {  
    function session_status() {  
        return session_id() === '' ? PHP_SESSION_NONE : PHP_SESSION_ACTIVE;  
    }  
}  
// Compute base URL for dynamic linking
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$baseDir = '/ecommerce/'; // <-- Set this to your VTU root directory (with trailing slash)
$baseUrl = $protocol . $host . $baseDir;
if (session_status() === PHP_SESSION_NONE) {  
    session_start();  
}  
  
?>  
<!DOCTYPE html>  
<html>  
<head>  
    <meta charset="UTF-8">  
    <meta name="viewport" content="width=device-width, initial-scale=1.0">  
    <title>E-Shop</title>  
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" integrity="sha512-Evv84Mr4kqVGRNSgIGL/F/aIDqQb7xQ2vcrdIwxfjThSH8CSR7PBEakCr51Ck+w+/U6swU2Im1vVX0SVk9ABhg==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css" rel="stylesheet">  
    <link href="<?php echo $baseUrl; ?>assets/css/styles.css" rel="stylesheet">  
    <script src="<?php echo $baseUrl; ?>assets/js/scripts.js" defer></script>

</head>  
<body>  
    <nav class="navbar navbar-expand-lg navbar-light bg-light">  
        <div class="container-fluid">  
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a class="navbar-brand" href="<?php echo $baseUrl; ?>admin/index.php">E-Shop</a>
            <?php else: ?>
                <a class="navbar-brand" href="<?php echo $baseUrl; ?>index.php">E-Shop</a>
            <?php endif; ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">  
                <span class="navbar-toggler-icon"></span>  
            </button>  
            <div class="collapse navbar-collapse" id="navbarNav">  
                <ul class="navbar-nav me-auto">  
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>  
                        <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/index.php">Dashboard</a></li>  
                        <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/products.php">Manage Products</a></li>  
                        <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/orders.php">Orders</a></li>  
                        <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/inventory.php">Inventory</a></li>  
                        <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/categories.php">Categories</a></li>  
                        <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/discounts.php">Discounts</a></li>  
                        <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/reviews.php">Reviews</a></li>  
                        <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>admin/users.php">Users</a></li>  
                    <?php else: ?>

                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>products.php">Products</a></li>  
                    <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>cart/cart.php">Cart <span class="cart-count badge" style="background-color: #f85606;"><?php echo isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0; ?></span></a></li>
                    <?php if (isset($_SESSION['user_id'])): ?>  
                            <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>user/orders.php">My Orders</a></li>  
                        <?php endif; ?>  
                    <?php endif; ?>  
                </ul>  
                <!-- Search Bar -->  
                <form class="d-flex" action="<?php echo $baseUrl; ?>search.php" method="GET">  
                    <input type="text" id="search" name="q" class="form-control me-2" placeholder="Search products..." autocomplete="off">  
                    <button type="submit" class="btn btn-outline-success">Search</button>  
                </form>  
                <ul class="navbar-nav ms-3">  
                    <?php if (isset($_SESSION['user_id'])): ?>  
                        <li class="nav-item"><span class="nav-link">Hello, <?php echo htmlspecialchars($_SESSION['username'] ?? '');?></span></li>  
                        <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>user/logout.php">Logout</a></li>  
                    <?php else: ?>  
                        <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>user/login.php">Login</a></li>  
                        <li class="nav-item"><a class="nav-link" href="<?php echo $baseUrl; ?>user/register.php">Register</a></li>  
                    <?php endif; ?>  
                </ul>  
            </div>  
        </div>  
    </nav>  
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>  
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>  
<script src="<?php echo $baseUrl; ?>assets/js/scripts.js" defer></script>
<script>  
        $(function() {  
            $("#search").autocomplete({  
                source: "<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); ?>/search_autocomplete.php",  
                minLength: 2,  
                select: function(event, ui) {  
                    window.location.href = "<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); ?>/view_product.php?id=" + ui.item.id;  
                }  
            });  
        });  
        $(window).on('load', function() {  
            $('.lazy').each(function() {  
                var img = $(this);  
                img.attr('src', img.data('src'));  
            });  
        });  
    </script>