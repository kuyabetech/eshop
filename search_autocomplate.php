<?php
require '../includes/config.php';

// Get search term from query string
$term = filter_input(INPUT_GET, 'term', FILTER_SANITIZE_STRING);
if (!$term) {
    echo json_encode([]);
    exit;
}

// Fetch matching products
try {
    $stmt = $pdo->prepare("SELECT id, name FROM products WHERE name LIKE ? AND stock > 0 LIMIT 10");
    $stmt->execute(["%$term%"]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for jQuery UI Autocomplete
    $results = array_map(function($product) {
        return [
            'label' => $product['name'],
            'value' => $product['name'],
            'id' => $product['id']
        ];
    }, $products);

    echo json_encode($results);
} catch (PDOException $e) {
    error_log("Search autocomplete error: " . $e->getMessage());
    echo json_encode([]);
}
exit;
?>