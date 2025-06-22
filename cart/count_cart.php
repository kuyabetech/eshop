<?php
require '../includes/config.php';
$cart = $_SESSION['cart'] ?? [];
$count = array_sum($cart);
echo json_encode(['count' => $count]);
?>