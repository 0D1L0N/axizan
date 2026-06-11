<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$pdo = require __DIR__ . '/db.php';
$action = $_GET['action'] ?? 'products';

function jsonResponse($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

switch ($action) {
    case 'products':
        $stmt = $pdo->query('SELECT * FROM products WHERE available = 1 ORDER BY category, name');
        jsonResponse(['products' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);

    case 'createOrder':
        $payload = json_decode(file_get_contents('php://input'), true);
        if (!$payload || empty($payload['customer_name']) || empty($payload['phone']) || empty($payload['address']) || empty($payload['items'])) {
            jsonResponse(['error' => 'Invalid payload'], 400);
        }

        $items = $payload['items'];
        if (!is_array($items) || count($items) === 0) {
            jsonResponse(['error' => 'Cart is empty'], 400);
        }

        $total = 0;
        $stmt = $pdo->prepare('SELECT id, price, name FROM products WHERE id = :id AND available = 1');
        foreach ($items as $item) {
            $stmt->execute([':id' => $item['id']]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) {
                jsonResponse(['error' => 'Product not available: ' . $item['id']], 400);
            }
            $total += (int)$product['price'] * (int)$item['quantity'];
        }

        $orderStmt = $pdo->prepare('INSERT INTO orders (customer_name, phone, address, total, status, created_at) VALUES (:customer_name, :phone, :address, :total, :status, :created_at)');
        $orderStmt->execute([
            ':customer_name' => trim($payload['customer_name']),
            ':phone' => trim($payload['phone']),
            ':address' => trim($payload['address']),
            ':total' => $total,
            ':status' => 'PENDING',
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        $orderId = (int)$pdo->lastInsertId();
        $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (:order_id, :product_id, :quantity, :price)');
        foreach ($items as $item) {
            $itemStmt->execute([
                ':order_id' => $orderId,
                ':product_id' => $item['id'],
                ':quantity' => $item['quantity'],
                ':price' => $item['price'],
            ]);
        }

        jsonResponse(['success' => true, 'order_id' => $orderId]);

    case 'orders':
        $customer = trim((string)($_GET['customer'] ?? ''));
        if ($customer === '') {
            jsonResponse(['error' => 'Customer name is required'], 400);
        }

        $stmt = $pdo->prepare('SELECT * FROM orders WHERE customer_name = :customer ORDER BY created_at DESC');
        $stmt->execute([':customer' => $customer]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($orders as &$order) {
            $itemStmt = $pdo->prepare('SELECT oi.quantity, oi.price, p.name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = :order_id');
            $itemStmt->execute([':order_id' => $order['id']]);
            $order['items'] = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
        }

        jsonResponse(['orders' => $orders]);

    default:
        jsonResponse(['error' => 'Action not found'], 404);
}
