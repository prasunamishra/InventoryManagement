<?php
require_once __DIR__ . '/../config/db.php';        // database connection
require_once __DIR__ . '/../config/helpers.php';  // helper functions

// RBAC CHECK 
// user direct database write garna milxa ki nai check
function logisticsCallerCanWriteDirectly(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start(); // session start
    }

    $role = $_SESSION['role'] ?? '';
    $jobRole = $_SESSION['job_role'] ?? '';

    // admin ya supervisor matra direct write garna milxa
    return $role === 'admin' || strtolower($jobRole) === 'supervisor';
}

//  SUBMIT REQUEST -
// direct write milena vane pending request pathaune
function logisticsSubmitPendingRequest(string $actionType, array $payload, string $description): array
{
    require_once __DIR__ . '/RequestController.php';

    return submitRequest([
        'action_type' => $actionType,
        'payload' => $payload,
        'description' => $description,
    ]);
}

//  GET ALL ORDERS 
// sabai logistics orders + items fetch garne
function getLogistics()
{
    global $pdo;

    // order list lina
    $orders = $pdo->query(
        "SELECT * FROM logistics ORDER BY created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!$orders) {
        return ["success" => true, "logistics" => []];
    }

    // sabai order_id collect garne
    $ids = array_column($orders, 'id');

    // multiple id ko lagi placeholder banaune (?, ?, ?)
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    // order items fetch garne
    $itemStmt = $pdo->prepare(
        "SELECT * FROM order_items WHERE order_id IN ($placeholders) ORDER BY id"
    );
    $itemStmt->execute($ids);
    $allItems = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

    // items group garne (order_id wise)
    $itemMap = [];
    foreach ($allItems as $item) {
        $itemMap[$item['order_id']][] = $item;
    }

    // each order ma items attach garne
    foreach ($orders as &$order) {
        $order['items'] = $itemMap[$order['id']] ?? [];

        // display ko lagi product summary banaune
        $order['product'] = implode(', ', array_map(
            fn($i) => $i['product_name'] . ' x' . $i['quantity'],
            $order['items']
        ));
    }

    return ["success" => true, "logistics" => $orders];
}

//  CHECK STOCK 
// product ko current stock calculate garne
function checkProductStock($pdo, $productId)
{
    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN type='IN'  THEN quantity ELSE 0 END), 0)
          - COALESCE(SUM(CASE WHEN type='OUT' THEN quantity ELSE 0 END), 0)
         FROM stock_ledger WHERE product_id = ?"
    );
    $stmt->execute([$productId]);

    return (int) $stmt->fetchColumn();
}

//  CREATE ORDER 
// new logistics order create garne
function createLogistics($data)
{
    global $pdo;

    // यदि staff ho vane direct create garna mildaina → request pathaune
    if (!logisticsCallerCanWriteDirectly()) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $customer = trim($data['customer'] ?? 'Unknown');
        $invoice = strtoupper(trim($data['invoice_number'] ?? ''));
        $itemCount = count($data['items'] ?? []);

        return logisticsSubmitPendingRequest(
            'create_order',
            $data,
            "Create order for '{$customer}' (Invoice: {$invoice}, {$itemCount} item(s))"
        );
    }

    // data extract garne
    $customer = trim($data['customer'] ?? '');
    $address = trim($data['address'] ?? '');
    $status = trim($data['status'] ?? 'Pending');
    $invoiceNumber = strtoupper(trim($data['invoice_number'] ?? ''));
    $notes = trim($data['notes'] ?? '');
    $items = $data['items'] ?? [];

    // valid status check
    $allowed = ['Pending', 'In Transit', 'Shipped', 'Delivered', 'Delayed'];
    if (!in_array($status, $allowed)) {
        $status = 'Pending';
    }

    // validation
    if (empty($customer) || empty($address)) {
        return ["success" => false, "message" => "Customer and address required."];
    }
    if (empty($invoiceNumber)) {
        return ["success" => false, "message" => "Invoice required."];
    }
    if (empty($items)) {
        return ["success" => false, "message" => "At least one item required."];
    }

    // duplicate invoice check
    $dup = $pdo->prepare("SELECT id FROM logistics WHERE UPPER(TRIM(invoice_number)) = ?");
    $dup->execute([$invoiceNumber]);
    if ($dup->fetch()) {
        return ["success" => false, "message" => "Duplicate invoice."];
    }

    // stock validation
    foreach ($items as $i => $item) {
        $pid = (int) ($item['product_id'] ?? 0);
        $qty = (int) ($item['quantity'] ?? 0);

        if (!$pid || $qty <= 0) {
            return ["success" => false, "message" => "Invalid item data."];
        }

        $stock = checkProductStock($pdo, $pid);
        if ($qty > $stock) {
            return ["success" => false, "message" => "Not enough stock."];
        }
    }

    // transaction start
    $pdo->beginTransaction();

    try {
        // order insert
        $stmt = $pdo->prepare(
            "INSERT INTO logistics (customer, invoice_number, address, status, notes)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$customer, $invoiceNumber, $address, $status, $notes]);

        $orderId = (int) $pdo->lastInsertId();

        // items insert
        foreach ($items as $item) {
            $pid = (int) $item['product_id'];
            $qty = (int) $item['quantity'];

            $pdo->prepare(
                "INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price)
                 VALUES (?, ?, ?, ?, ?)"
            )->execute([$orderId, $pid, 'Product', $qty, 0]);

            // Delivered vaye stock OUT garne
            if ($status === 'Delivered') {
                $pdo->prepare(
                    "INSERT INTO stock_ledger (product_id, type, quantity)
                     VALUES (?, 'OUT', ?)"
                )->execute([$pid, $qty]);
            }
        }

        $pdo->commit();

        return ["success" => true, "message" => "Order created.", "id" => $orderId];

    } catch (Exception $e) {
        $pdo->rollBack();
        return ["success" => false, "message" => "Error: " . $e->getMessage()];
    }
}

//  UPDATE ORDER 
function updateLogistics($data)
{
    global $pdo;

    // staff ho vane request pathaune
    if (!logisticsCallerCanWriteDirectly()) {
        return logisticsSubmitPendingRequest(
            'update_order',
            $data,
            "Update order"
        );
    }

    $id = (int) ($data['id'] ?? 0);
    $status = trim($data['status'] ?? '');

    if (!$id) {
        return ["success" => false, "message" => "ID required."];
    }

    $pdo->prepare("UPDATE logistics SET status = ? WHERE id = ?")
        ->execute([$status, $id]);

    return ["success" => true, "message" => "Order updated."];
}

//  DELETE ORDER 
function deleteLogistics($data)
{
    global $pdo;

    // staff ho vane request pathaune
    if (!logisticsCallerCanWriteDirectly()) {
        return logisticsSubmitPendingRequest(
            'delete_order',
            $data,
            "Delete order"
        );
    }

    $id = (int) ($data['id'] ?? 0);

    if (!$id) {
        return ["success" => false, "message" => "ID required."];
    }

    // order delete garne
    $pdo->prepare("DELETE FROM logistics WHERE id = ?")->execute([$id]);

    return ["success" => true, "message" => "Order deleted."];
}