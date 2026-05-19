<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// ── Auto-generate next PR invoice number ──────────────────────────────────────
function generatePRInvoice($pdo) {
    $stmt = $pdo->query("SELECT invoice_number FROM purchase_returns ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $next = 1;
    if ($last && preg_match('/PR-(\d+)$/', $last, $m)) {
        $next = (int)$m[1] + 1;
    }
    // Ensure uniqueness
    do {
        $candidate = 'PR-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        $chk = $pdo->prepare("SELECT id FROM purchase_returns WHERE invoice_number = ? LIMIT 1");
        $chk->execute([$candidate]);
        if (!$chk->fetch()) break;
        $next++;
    } while (true);
    return $candidate;
}

// ── GET: list all purchase returns ────────────────────────────────────────────
function getPurchaseReturns() {
    global $pdo;
    $rows = $pdo->query(
        "SELECT * FROM purchase_returns ORDER BY created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    return ["success" => true, "returns" => $rows];
}

// ── GET: next available invoice number ────────────────────────────────────────
function getNextPRInvoice() {
    global $pdo;
    return ["success" => true, "invoice_number" => generatePRInvoice($pdo)];
}

// ── POST: create a purchase return ───────────────────────────────────────────
function createPurchaseReturn($data) {
    global $pdo;

    $invoice  = strtoupper(trim($data['invoice_number'] ?? ''));
    $supplier = trim($data['supplier']  ?? '');
    $item     = trim($data['item_name'] ?? '');
    $category = trim($data['category']  ?? '');
    $qty      = (int)($data['quantity'] ?? 0);

    // ── Required field validation ────────────────────────────────────────────
    if (!$supplier || !$item || !$category) {
        return ["success" => false, "message" => "Please fill in all required fields.", "_code" => 400];
    }
    if ($qty <= 0) {
        return ["success" => false, "message" => "Please fill in all required fields.", "_code" => 400];
    }
    if (empty($invoice)) {
        return ["success" => false, "message" => "Please fill in all required fields.", "_code" => 400];
    }

    // ── Invoice duplicate check ──────────────────────────────────────────────
    $dupChk = $pdo->prepare("SELECT id FROM purchase_returns WHERE UPPER(TRIM(invoice_number)) = ? LIMIT 1");
    $dupChk->execute([$invoice]);
    if ($dupChk->fetch()) {
        return ["success" => false, "message" => "Invoice number already used. Please generate a new one.", "_code" => 409];
    }

    // ── Find matching product in inventory ───────────────────────────────────
    $prodStmt = $pdo->prepare(
        "SELECT id, stock FROM products WHERE LOWER(TRIM(name)) = LOWER(?) AND approval_status = 'approved' LIMIT 1"
    );
    $prodStmt->execute([$item]);
    $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        // Try substring match as fallback
        $prodStmt2 = $pdo->prepare(
            "SELECT id, stock FROM products WHERE LOWER(name) LIKE ? AND approval_status = 'approved' LIMIT 1"
        );
        $prodStmt2->execute(['%' . strtolower($item) . '%']);
        $product = $prodStmt2->fetch(PDO::FETCH_ASSOC);
    }

    // ── Stock check ──────────────────────────────────────────────────────────
    if ($product) {
        $currentStock = (int)$product['stock'];
        if ($qty > $currentStock) {
            return [
                "success" => false,
                "message" => "Return quantity exceeds available stock. Only {$currentStock} unit" . ($currentStock !== 1 ? 's' : '') . " in stock.",
                "_code"   => 422
            ];
        }
    }

    // ── Insert return record ─────────────────────────────────────────────────
    $ins = $pdo->prepare(
        "INSERT INTO purchase_returns (invoice_number, supplier, item_name, category, quantity, product_id)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $ins->execute([
        $invoice, $supplier, $item, $category, $qty,
        $product ? $product['id'] : null
    ]);
    $returnId = $pdo->lastInsertId();

    // ── Deduct stock ─────────────────────────────────────────────────────────
    if ($product) {
        $deduct = $pdo->prepare("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?");
        $deduct->execute([$qty, $product['id']]);
    }

    return [
        "success"        => true,
        "message"        => "Purchase return recorded successfully. Stock has been updated.",
        "id"             => $returnId,
        "invoice_number" => $invoice
    ];
}
