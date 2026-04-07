<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

function getProducts() {
    global $pdo;
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $userId = $_SESSION['user_id'] ?? 0;
    
    $rows = $pdo->query("SELECT * FROM products WHERE approval_status = 'approved' ORDER BY created_at DESC")->fetchAll();
    
    $rejected = [];
    if ($userId) {
        $stmt = $pdo->prepare("SELECT id, name FROM products WHERE added_by = ? AND approval_status = 'rejected' AND is_notified = 0");
        $stmt->execute([$userId]);
        $rejected = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    return ["success" => true, "products" => $rows, "rejectedItems" => $rejected];
}

function createProduct($data) {
    global $pdo;
    $name     = trim($data['name']     ?? '');
    $category = trim($data['category'] ?? '');
    $stock    = (int)   ($data['stock']    ?? 0);
    $price    = (float) ($data['price']   ?? 0);
    $cost     = (float) ($data['cost']    ?? 0);
    $supplier = trim($data['supplier'] ?? '');
    $storage  = trim($data['storage']  ?? '');

    if (!$name || !$category) {
        return ["success" => false, "message" => "Product name and category are required.", "_code" => 400];
    }

    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $userId = $_SESSION['user_id'] ?? 0;
    $role = $_SESSION['role'] ?? '';
    $jobRole = $_SESSION['job_role'] ?? '';
    $isAdmin = $role === 'admin' || $jobRole === 'supervisor';
    
    $approval_status = $isAdmin ? 'approved' : 'pending';
    $added_by = $userId;

    $prefix = strtoupper(substr($category, 0, 3));
    do {
        $sku = $prefix . '-' . rand(10000, 99999);
        $exists = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
        $exists->execute([$sku]);
    } while ($exists->fetch());

    $stmt = $pdo->prepare(
        "INSERT INTO products (sku, name, category, stock, price, cost, supplier, storage, approval_status, added_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$sku, $name, $category, $stock, $price, $cost, $supplier, $storage, $approval_status, $added_by]);

    $msg = $isAdmin ? "Product added." : "Product submitted for admin approval.";
    return ["success" => true, "message" => $msg, "id" => $pdo->lastInsertId(), "sku" => $sku];
}

function updateProduct($data) {
    global $pdo;
    $id       = (int)   ($data['id']       ?? 0);
    $name     = trim($data['name']         ?? '');
    $category = trim($data['category']     ?? '');
    $stock    = (int)   ($data['stock']    ?? 0);
    $price    = (float) ($data['price']    ?? 0);
    $cost     = (float) ($data['cost']     ?? 0);
    $supplier = trim($data['supplier']     ?? '');
    $storage  = trim($data['storage']      ?? '');

    if (!$id || !$name || !$category) {
        return ["success" => false, "message" => "ID, name and category are required.", "_code" => 400];
    }

    $stmt = $pdo->prepare(
        "UPDATE products
         SET name = ?, category = ?, stock = ?, price = ?, cost = ?, supplier = ?, storage = ?
         WHERE id = ?"
    );
    $stmt->execute([$name, $category, $stock, $price, $cost, $supplier, $storage, $id]);

    return ["success" => true, "message" => "Product updated."];
}

function deleteProduct($data) {
    global $pdo;
    $id = (int) ($data['id'] ?? 0);

    if (!$id) {
        return ["success" => false, "message" => "Product ID is required.", "_code" => 400];
    }

    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $role = $_SESSION['role'] ?? '';
    $jobRole = $_SESSION['job_role'] ?? '';
    $isAdmin = $role === 'admin' || $jobRole === 'supervisor';
    if (!$isAdmin) {
        return ["success" => false, "message" => "Permission denied to delete products.", "_code" => 403];
    }

    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        return ["success" => false, "message" => "Product not found.", "_code" => 404];
    }

    return ["success" => true, "message" => "Product deleted."];
}
