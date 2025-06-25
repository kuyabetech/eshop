<?php
require '../includes/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../user/login.php?error=Unauthorized access");
    exit;
}

function sendOrderToSupplier($order, $supplier, $items) {
    // Mock API call (replace with actual supplier API)
    $data = [
        'order_id' => $order['id'],
        'items' => array_map(function($item) {
            return [
                'product_id' => $item['supplier_product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['supplier_price']
            ];
        }, $items),
        'shipping' => [
            'first_name' => $order['shipping_first_name'],
            'last_name' => $order['shipping_last_name'],
            'address' => $order['shipping_address'],
            'city' => $order['shipping_city'],
            'postal_code' => $order['shipping_postal_code']
        ]
    ];
    $ch = curl_init($supplier['api_url'] . '/orders?key=' . $supplier['api_key']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200) {
        error_log("Supplier API error: HTTP $http_code for {$supplier['api_url']}");
        return ['success' => false, 'error' => 'API request failed'];
    }

    // Mock response for testing
    return json_decode('{"success":true,"supplier_order_id":"SUP123"}', true);
}

$order_id = filter_input(INPUT_GET, 'order_id', FILTER_VALIDATE_INT);
if (!$order_id) {
    header("Location: dashboard.php?error=Invalid order ID");
    exit;
}

try {
    // Fetch order with shipping details
    $stmt = $pdo->prepare("
        SELECT o.*, u.username, u.address AS shipping_address, 
               u.first_name AS shipping_first_name, u.last_name AS shipping_last_name,
               u.city AS shipping_city, u.postal_code AS shipping_postal_code
        FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        header("Location: dashboard.php?error=Order not found");
        exit;
    }

    // Get order items with supplier details
    $stmt = $pdo->prepare("
        SELECT oi.*, sp.supplier_product_id, sp.supplier_price, s.id AS supplier_id, s.name, s.api_key, s.api_url
        FROM order_items oi
        JOIN supplier_products sp ON oi.product_id = sp.product_id
        JOIN suppliers s ON sp.supplier_id = s.id
        WHERE oi.order_id = ?
    ");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    if (empty($items)) {
        header("Location: dashboard.php?error=No dropship items in order");
        exit;
    }

    // Group items by supplier
    $items_by_supplier = [];
    foreach ($items as $item) {
        $items_by_supplier[$item['supplier_id']][] = $item;
    }

    $pdo->beginTransaction();
    foreach ($items_by_supplier as $supplier_id => $supplier_items) {
        $supplier = [
            'id' => $supplier_id,
            'name' => $supplier_items[0]['name'],
            'api_key' => $supplier_items[0]['api_key'],
            'api_url' => $supplier_items[0]['api_url']
        ];
        $response = sendOrderToSupplier($order, $supplier, $supplier_items);
        if ($response['success']) {
            $stmt = $pdo->prepare("UPDATE orders SET supplier_order_id = ?, fulfillment_status = 'fulfilled' WHERE id = ?");
            $stmt->execute([$response['supplier_order_id'], $order_id]);
        } else {
            $pdo->rollBack();
            error_log("Order fulfillment error for supplier {$supplier['name']}: " . json_encode($response));
            header("Location: dashboard.php?error=Failed to fulfill order");
            exit;
        }
    }
    $pdo->commit();
    // Invalidate cache
    if (class_exists('Redis')) {
        try {
            $redis = new Redis();
            if ($redis->connect('localhost', 6379)) {
                $redis->del("dashboard_metrics");
            }
        } catch (Exception $e) {
            error_log("Redis connection error: " . $e->getMessage());
        }
    } else {
        error_log("Redis extension not installed or enabled.");
    }
    header("Location: dashboard.php?success=Order fulfilled successfully");
    exit;
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Fulfillment error: " . $e->getMessage());
    header("Location: dashboard.php?error=Database error");
    exit;
}
?>