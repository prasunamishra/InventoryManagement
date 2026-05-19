<?php
require_once __DIR__ . '/../config/db.php';        // database connection
require_once __DIR__ . '/../config/helpers.php';  // helper functions

//  RBAC CHECK
// yo function le check garxa user direct product modify garna milxa ki nai
function productCallerCanWriteDirectly(): bool {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    $role    = $_SESSION['role']     ?? '';
    $jobRole = $_SESSION['job_role'] ?? '';

    // admin ya supervisor matra direct change garna paauxa
    return $role === 'admin' || strtolower($jobRole) === 'supervisor';
}

//  REQUEST SYSTEM 
// direct access chaina vane pending request create garne
function productSubmitPendingRequest(string $actionType, array $payload, string $description): array {
    require_once __DIR__ . '/RequestController.php';

    return submitRequest([
        'action_type' => $actionType, // k action ho (create/update/delete)
        'payload'     => $payload,    // actual data
        'description' => $description, // description for admin
    ]);
}

//  GET PRODUCTS 
// sabai approved product + stock + latest invoice lina
function getProducts() {
    global $pdo;

    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $userId = $_SESSION['user_id'] ?? 0;

    // product + stock calculation
    $rows = $pdo->query(
        "SELECT p.*,
            (SELECT pur.invoice_number 
             FROM purchase_items pi 
             JOIN purchases pur ON pur.id = pi.purchase_id 
             WHERE pi.product_id = p.id 
             ORDER BY pur.purchase_date DESC LIMIT 1) as latest_invoice,

            -- stock = total IN - total OUT
            COALESCE(SUM(CASE WHEN sl.type='IN' THEN sl.quantity ELSE 0 END), 0)
          - COALESCE(SUM(CASE WHEN sl.type='OUT' THEN sl.quantity ELSE 0 END), 0) AS stock

         FROM products p
         LEFT JOIN stock_ledger sl ON sl.product_id = p.id
         WHERE p.approval_status = 'approved'
         GROUP BY p.id
         ORDER BY p.created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    // rejected product notification (user lai dekhau)
    $rejected = [];
    if ($userId) {
        $stmt = $pdo->prepare(
            "SELECT id, name FROM products 
             WHERE added_by = ? AND approval_status = 'rejected' AND is_notified = 0"
        );
        $stmt->execute([$userId]);
        $rejected = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return ["success" => true, "products" => $rows, "rejectedItems" => $rejected];
}

//  CREATE PRODUCT 
// single product lai bulk format ma convert garxa
function createProduct($data) {

    // bulk create ho vane direct call
    if (isset($data['action']) && $data['action'] === 'bulk_create') {
        return bulkCreateProducts($data);
    }

    // single product lai array ma convert
    $data['products'] = [[
        'name' => trim($data['name'] ?? ''),
        'category' => $data['category'] ?? '',
        'price' => $data['price'] ?? 0,
        'cost' => $data['cost'] ?? 0,
        'qty' => $data['initial_qty'] ?? 0
    ]];

    return bulkCreateProducts($data);
}

//  BULK CREATE 
function bulkCreateProducts($data) {
    global $pdo;

    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    $role    = $_SESSION['role'] ?? '';
    $jobRole = $_SESSION['job_role'] ?? '';

    $isAdmin = $role === 'admin' || strtolower($jobRole) === 'supervisor';

    // staff le direct create garna paaudaina → request pathaune
    if (!$isAdmin && empty($data['_bypass_approval'])) {
        return productSubmitPendingRequest(
            'create_product',
            $data,
            "Product add request"
        );
    }

    $supplier = trim($data['supplier'] ?? '');
    $invoice  = strtoupper(trim($data['invoice_number'] ?? ''));
    $items    = $data['products'] ?? [];

    if (!$supplier || !$invoice || empty($items)) {
        return ["success" => false, "message" => "Required data missing"];
    }

    $pdo->beginTransaction();

    try {
        // purchase header create
        $pdo->prepare(
            "INSERT INTO purchases (invoice_number, supplier_name)
             VALUES (?, ?)"
        )->execute([$invoice, $supplier]);

        $purchaseId = $pdo->lastInsertId();

        foreach ($items as $item) {
            $name = trim($item['name']);
            $qty  = (int)$item['qty'];

            if (!$name || $qty <= 0) {
                throw new Exception("Invalid product data");
            }

            // product create
            $pdo->prepare(
                "INSERT INTO products (name, approval_status)
                 VALUES (?, 'approved')"
            )->execute([$name]);

            $productId = $pdo->lastInsertId();

            // stock IN add garne
            $pdo->prepare(
                "INSERT INTO stock_ledger (product_id, type, quantity)
                 VALUES (?, 'IN', ?)"
            )->execute([$productId, $qty]);
        }

        $pdo->commit();

        return ["success" => true, "message" => "Products added successfully"];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ["success" => false, "message" => $e->getMessage()];
    }
}

//  UPDATE PRODUCT 
function updateProduct($data) {
    global $pdo;

    // staff ho vane request pathaune
    if (!productCallerCanWriteDirectly()) {
        return productSubmitPendingRequest('update_product', $data, "Update request");
    }

    $id = (int)($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');

    if (!$id || !$name) {
        return ["success" => false, "message" => "Invalid data"];
    }

    $pdo->prepare("UPDATE products SET name = ? WHERE id = ?")
        ->execute([$name, $id]);

    return ["success" => true, "message" => "Product updated"];
}

//  DELETE PRODUCT 
function deleteProduct($data) {
    global $pdo;

    $id = (int)($data['id'] ?? 0);

    if (!$id) {
        return ["success" => false, "message" => "ID required"];
    }

    // staff → request system
    if (!productCallerCanWriteDirectly()) {
        return productSubmitPendingRequest('delete_product', $data, "Delete request");
    }

    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);

    return ["success" => true, "message" => "Product deleted"];
}

// UPDATE STATUS 
function updateProductStatus($data) {
    global $pdo;

    $id = (int)($data['id'] ?? 0);
    $status = trim($data['status'] ?? '');

    if (!$id || !in_array($status, ['active', 'inactive'])) {
        return ["success" => false, "message" => "Invalid input"];
    }

    if (!productCallerCanWriteDirectly()) {
        return productSubmitPendingRequest('update_product_status', $data, "Status change request");
    }

    $pdo->prepare("UPDATE products SET status = ? WHERE id = ?")
        ->execute([$status, $id]);

    return ["success" => true, "message" => "Status updated"];
}

//  APPROVAL 
// admin/supervisor le approve/reject garne
function updateApprovalStatus($data) {
    global $pdo;

    if (session_status() === PHP_SESSION_NONE) { session_start(); }

    $role = $_SESSION['role'] ?? '';

    if ($role !== 'admin') {
        return ["success" => false, "message" => "Permission denied"];
    }

    $id = (int)($data['id'] ?? 0);
    $status = trim($data['approval_status'] ?? '');

    if (!$id || !in_array($status, ['approved', 'rejected'])) {
        return ["success" => false, "message" => "Invalid input"];
    }

    $pdo->prepare("UPDATE products SET approval_status = ? WHERE id = ?")
        ->execute([$status, $id]);

    return ["success" => true, "message" => "Approval updated"];
}