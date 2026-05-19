<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// 1. Get all suppliers ordered by creation date
function getSuppliers() {
    global $pdo;
    $rows = $pdo->query("SELECT *, status FROM suppliers ORDER BY created_at DESC")->fetchAll();
    return ["success" => true, "suppliers" => $rows];
}

// 2. Create and validate a new supplier entry
function createSupplier($data) {
    global $pdo;

    $name  = trim($data['name']  ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');

    if (empty($name)) {
        return ["success" => false, "message" => "Supplier name is required.", "_code" => 400];
    }
    if (!preg_match('/^\d{10}$/', $phone)) {
        return ["success" => false, "message" => "Phone number must be exactly 10 digits.", "_code" => 400];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.com$/i', $email)) {
        return ["success" => false, "message" => "Please provide a valid .com email address.", "_code" => 400];
    }

    $dupStmt = $pdo->prepare(
        "SELECT id FROM suppliers
         WHERE LOWER(TRIM(name))  = LOWER(?)
            OR TRIM(phone)        = ?
            OR LOWER(TRIM(email)) = LOWER(?)
         LIMIT 1"
    );
    $dupStmt->execute([$name, $phone, $email]);
    if ($dupStmt->fetch()) {
        return ["success" => false, "message" => "Supplier already exists. Duplicate entries are not allowed.", "_code" => 409];
    }

    $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email) VALUES (?, ?, ?)");
    $stmt->execute([$name, $phone, $email]);
    return ["success" => true, "message" => "Supplier added successfully.", "id" => $pdo->lastInsertId()];
}

// 3. Update an existing supplier's details
function updateSupplier($data) {
    global $pdo;

    $id    = (int)($data['id']    ?? 0);
    $name  = trim($data['name']   ?? '');
    $phone = trim($data['phone']  ?? '');
    $email = trim($data['email']  ?? '');

    if (!$id) {
        return ["success" => false, "message" => "Supplier ID and name are required.", "_code" => 400];
    }
    if (empty($name)) {
        return ["success" => false, "message" => "Supplier ID and name are required.", "_code" => 400];
    }
    if (!preg_match('/^\d{10}$/', $phone)) {
        return ["success" => false, "message" => "Phone number must be exactly 10 digits.", "_code" => 400];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.com$/i', $email)) {
        return ["success" => false, "message" => "Please provide a valid .com email address.", "_code" => 400];
    }

    $dupStmt = $pdo->prepare(
        "SELECT id FROM suppliers
         WHERE (LOWER(TRIM(name))  = LOWER(?)
             OR TRIM(phone)        = ?
             OR LOWER(TRIM(email)) = LOWER(?))
           AND id != ?
         LIMIT 1"
    );
    $dupStmt->execute([$name, $phone, $email, $id]);
    if ($dupStmt->fetch()) {
        return ["success" => false, "message" => "Supplier already exists. Duplicate entries are not allowed.", "_code" => 409];
    }

    $pdo->prepare("UPDATE suppliers SET name = ?, phone = ?, email = ? WHERE id = ?")->execute([$name, $phone, $email, $id]);
    return ["success" => true, "message" => "Supplier updated successfully."];
}

// 4. Update a supplier's status (Activate / Deactivate)
// We use soft-deletes here (active/inactive) so we never lose the connection 
// to past purchase orders or stock entries that belong to this supplier.
function updateSupplierStatus($data) {
    global $pdo;
    $id = (int)($data['id'] ?? 0);
    $status = trim($data['status'] ?? '');
    
    // Validate inputs before we do anything
    if (!$id) {
        return ["success" => false, "message" => "Supplier ID is required.", "_code" => 400];
    }
    if (!in_array($status, ['active', 'inactive'])) {
        return ["success" => false, "message" => "Invalid status.", "_code" => 400];
    }
    
    // Update the database record
    $pdo->prepare("UPDATE suppliers SET status = ? WHERE id = ?")->execute([$status, $id]);
    
    $action = $status === 'active' ? 'activated' : 'deactivated';
    return ["success" => true, "message" => "Supplier $action successfully."];
}
