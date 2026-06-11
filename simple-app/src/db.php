<?php

declare(strict_types=1);

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

$dbFile = $dataDir . '/app.sqlite';
$init = !file_exists($dbFile);
$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('PRAGMA foreign_keys = ON;');

if ($init) {
    $pdo->exec('CREATE TABLE products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        description TEXT NOT NULL,
        price INTEGER NOT NULL,
        category TEXT NOT NULL,
        available INTEGER NOT NULL DEFAULT 1
    );');

    $pdo->exec('CREATE TABLE orders (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        customer_name TEXT NOT NULL,
        phone TEXT NOT NULL,
        address TEXT NOT NULL,
        total INTEGER NOT NULL,
        status TEXT NOT NULL DEFAULT "PENDING",
        created_at TEXT NOT NULL
    );');

    $pdo->exec('CREATE TABLE order_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        order_id INTEGER NOT NULL,
        product_id INTEGER NOT NULL,
        quantity INTEGER NOT NULL,
        price INTEGER NOT NULL,
        FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
    );');

    $products = [
        ['name' => 'Poulet rôti', 'description' => 'Poulet tendre servi avec riz et salade.', 'price' => 4200, 'category' => 'Plats', 'available' => 1],
        ['name' => 'Alloco + poisson', 'description' => 'Banane plantain frite avec poisson grillé.', 'price' => 3800, 'category' => 'Plats', 'available' => 1],
        ['name' => 'Akashta', 'description' => 'Beignets béninois pour un snack rapide.', 'price' => 1200, 'category' => 'Snacks', 'available' => 1],
        ['name' => 'Jus d’ananas', 'description' => 'Jus naturel frais et sucré.', 'price' => 900, 'category' => 'Boissons', 'available' => 1],
        ['name' => 'Salade de fruits', 'description' => 'Fruits de saison coupés et servis frais.', 'price' => 1800, 'category' => 'Desserts', 'available' => 1],
    ];

    $stmt = $pdo->prepare('INSERT INTO products (name, description, price, category, available) VALUES (:name, :description, :price, :category, :available)');
    foreach ($products as $product) {
        $stmt->execute($product);
    }
}

return $pdo;
