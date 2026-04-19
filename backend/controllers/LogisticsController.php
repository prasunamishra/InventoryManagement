<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

function getLogistics() {
    global $pdo;
    $rows = $pdo->query("SELECT * FROM logistics ORDER BY created_at DESC")->fetchAll();
    return ["success" => true, "logistics" => $rows];
}

function createLogistics($data) {
    global $pdo;
    $customer = trim($data['customer'] ?? '');
    $address  = trim($data['address']  ?? '');
    $product  = trim($data['product']  ?? '');
    $status   = trim($data['status']   ?? 'Pending');

    $allowed = ['Pending', 'In Transit', 'Shipped', 'Delivered', 'Delayed'];
    if (!in_array($status, $allowed)) {
        $status = 'Pending';
    }

    if (!$customer || !$address || !$product) {
        return ["success" => false, "message" => "Customer, address and product are required.", "_code" => 400];
    }

    $stmt = $pdo->prepare("INSERT INTO logistics (customer, address, product, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$customer, $address, $product, $status]);

    return ["success" => true, "message" => "Order added.", "id" => $pdo->lastInsertId()];
}

function updateLogistics($data) {
    global $pdo;
    $id     = (int) ($data['id']     ?? 0);
    $status = trim($data['status']   ?? '');

    $allowed = ['Pending', 'In Transit', 'Shipped', 'Delivered', 'Delayed'];
    if (!$id || !in_array($status, $allowed)) {
        return ["success" => false, "message" => "Valid ID and status required.", "_code" => 400];
    }

    $stmt = $pdo->prepare("UPDATE logistics SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);

    return ["success" => true, "message" => "Status updated."];
}

function deleteLogistics($data) {
    global $pdo;
    $id = (int) ($data['id'] ?? 0);

    if (!$id) {
        return ["success" => false, "message" => "Order ID is required.", "_code" => 400];
    }

    $stmt = $pdo->prepare("DELETE FROM logistics WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        return ["success" => false, "message" => "Order not found.", "_code" => 404];
    }

    return ["success" => true, "message" => "Order deleted."];
}
