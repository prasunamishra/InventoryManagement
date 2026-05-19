<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

function getSuppliers() {
    global $pdo;
    $rows = $pdo->query("SELECT * FROM suppliers ORDER BY created_at DESC")->fetchAll();
    return ["success" => true, "suppliers" => $rows];
}

function createSupplier($data) {
    global $pdo;
    $name  = trim($data['name']  ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');

    if (!$name) {
        return ["success" => false, "message" => "Supplier name is required.", "_code" => 400];
    }

    if (!preg_match('/^\d{10}$/', $phone)) {
        return ["success" => false, "message" => "Phone number must be exactly 10 digits.", "_code" => 400];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.com$/i', $email)) {
        return ["success" => false, "message" => "Please provide a valid .com email address.", "_code" => 400];
    }

    $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email) VALUES (?, ?, ?)");
    $stmt->execute([$name, $phone, $email]);

    return ["success" => true, "message" => "Supplier added successfully.", "id" => $pdo->lastInsertId()];
}

function updateSupplier($data) {
    global $pdo;
    $id    = (int)($data['id'] ?? 0);
    $name  = trim($data['name']  ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');

    if (!$id || !$name) {
        return ["success" => false, "message" => "Supplier ID and name are required.", "_code" => 400];
    }

    if (!preg_match('/^\d{10}$/', $phone)) {
        return ["success" => false, "message" => "Phone number must be exactly 10 digits.", "_code" => 400];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.com$/i', $email)) {
        return ["success" => false, "message" => "Please provide a valid .com email address.", "_code" => 400];
    }

    $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, phone = ?, email = ? WHERE id = ?");
    $stmt->execute([$name, $phone, $email, $id]);

    return ["success" => true, "message" => "Supplier updated successfully."];
}

function deleteSupplier($data) {
    global $pdo;
    $id = (int) ($data['id'] ?? 0);
    if (!$id) {
        return ["success" => false, "message" => "Supplier ID is required.", "_code" => 400];
    }

    $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
    $stmt->execute([$id]);

    return ["success" => true, "message" => "Supplier deleted."];
}