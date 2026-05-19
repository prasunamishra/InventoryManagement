<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// returns every supplier, newest first
function getSuppliers() {
    global $pdo;
    $rows = $pdo->query("SELECT * FROM suppliers ORDER BY created_at DESC")->fetchAll();
    return ["success" => true, "suppliers" => $rows];
}

// adds a new supplier after validating all their info
function createSupplier($data) {
    global $pdo;

    $name  = trim($data['name']  ?? '');
    $phone = trim($data['phone'] ?? '');
    $email = trim($data['email'] ?? '');

    // supplier name is the bare minimum
    if (empty($name)) {
        return ["success" => false, "message" => "Supplier name is required.", "_code" => 400];
    }

    // phone must be exactly 10 digits - no spaces, dashes, or country codes
    if (!preg_match('/^\d{10}$/', $phone)) {
        return ["success" => false, "message" => "Phone number must be exactly 10 digits.", "_code" => 400];
    }

    // validate email format AND make sure it ends in .com
    // (a bit strict but it's what was decided for this project)
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.com$/i', $email)) {
        return ["success" => false, "message" => "Please provide a valid .com email address.", "_code" => 400];
    }

    // check for duplicates - same name OR same phone OR same email is not allowed
    // this prevents the same supplier being added under a slightly different name
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

    // everything looks good - insert the new supplier
    $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email) VALUES (?, ?, ?)");
    $stmt->execute([$name, $phone, $email]);
    return ["success" => true, "message" => "Supplier added successfully.", "id" => $pdo->lastInsertId()];
}

// updates an existing supplier's contact details
function updateSupplier($data) {
    global $pdo;

    $id    = (int)($data['id']    ?? 0);
    $name  = trim($data['name']   ?? '');
    $phone = trim($data['phone']  ?? '');
    $email = trim($data['email']  ?? '');

    // need both an ID and a name at minimum
    if (!$id) {
        return ["success" => false, "message" => "Supplier ID and name are required.", "_code" => 400];
    }
    if (empty($name)) {
        return ["success" => false, "message" => "Supplier ID and name are required.", "_code" => 400];
    }

    // same phone/email validation as when creating
    if (!preg_match('/^\d{10}$/', $phone)) {
        return ["success" => false, "message" => "Phone number must be exactly 10 digits.", "_code" => 400];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/\.com$/i', $email)) {
        return ["success" => false, "message" => "Please provide a valid .com email address.", "_code" => 400];
    }

    // check for duplicates BUT exclude the current supplier being edited
    // (a supplier is allowed to keep their own name/phone/email)
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

// permanently removes a supplier by ID
function deleteSupplier($data) {
    global $pdo;

    $id = (int)($data['id'] ?? 0);
    if (!$id) {
        return ["success" => false, "message" => "Supplier ID is required.", "_code" => 400];
    }

    $pdo->prepare("DELETE FROM suppliers WHERE id = ?")->execute([$id]);
    return ["success" => true, "message" => "Supplier deleted."];
}
