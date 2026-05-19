<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// generates the next PR invoice number like PR-0001, PR-0002, etc.
// it looks at the last invoice in the DB and just increments the number
function generatePRInvoice($pdo) {
    $stmt = $pdo->query("SELECT invoice_number FROM purchase_returns ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    $next = 1;

    // extract the numeric part from something like "PR-0042" and add 1
    if ($last && preg_match('/PR-(\d+)$/', $last, $m)) {
        $next = (int)$m[1] + 1;
    }

    // keep incrementing until we find a number that isn't already taken
    // (handles edge cases where records were deleted out of order)
    do {
        $candidate = 'PR-' . str_pad($next, 4, '0', STR_PAD_LEFT);
        $chk = $pdo->prepare("SELECT id FROM purchase_returns WHERE invoice_number = ? LIMIT 1");
        $chk->execute([$candidate]);
        if (!$chk->fetch()) break; // this number is free, use it
        $next++;
    } while (true);

    return $candidate;
}

// returns all purchase returns, newest first
function getPurchaseReturns() {
    global $pdo;
    $rows = $pdo->query(
        "SELECT * FROM purchase_returns ORDER BY created_at DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    return ["success" => true, "returns" => $rows];
}

// just returns the next available invoice number - used by the frontend
// when opening the "new return" form so it pre-fills the invoice field
function getNextPRInvoice() {
    global $pdo;
    return ["success" => true, "invoice_number" => generatePRInvoice($pdo)];
}

// ── ROLE-BASED ACCESS HELPERS ─────────────────────────────────────────────────

// returns true if the logged-in user can write directly to the DB
// (i.e. they're an admin or supervisor)
// regular staff have to submit a "pending request" instead
function returnCallerCanWriteDirectly(): bool {
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $role    = strtolower(trim($_SESSION['role']    ?? ''));
    $jobRole = strtolower(trim($_SESSION['job_role'] ?? ''));
    return ($role === 'admin' || $jobRole === 'supervisor');
}

// if a staff member tries to do something they can't do directly,
// we submit it as a pending request for an admin/supervisor to approve later
function returnSubmitPendingRequest(string $actionType, array $payload, string $desc): array {
    require_once __DIR__ . '/RequestController.php';
    return submitRequest([
        'action_type' => $actionType,
        'payload'     => $payload,
        'description' => $desc,
    ]);
}

// handles creating a new purchase return record
function createPurchaseReturn($data) {
    global $pdo;

    // pull and sanitize the fields from the request body
    $invoice  = strtoupper(trim($data['invoice_number'] ?? ''));
    $supplier = trim($data['supplier']  ?? '');
    $item     = trim($data['item_name'] ?? '');
    $category = trim($data['category']  ?? '');
    $qty      = (int)($data['quantity'] ?? 0);

    // if the user is a regular staff member, they can't create returns directly
    // so we wrap their request into a pending approval instead
    if (!returnCallerCanWriteDirectly()) {
        return returnSubmitPendingRequest(
            'create_purchase_return',
            $data,
            "Create Purchase Return {$invoice} for {$qty}x {$item}"
        );
    }

    // validate all required fields before touching the database
    if (!$supplier || !$item || !$category) {
        return ["success" => false, "message" => "Please fill in all required fields.", "_code" => 400];
    }
    if ($qty <= 0) {
        return ["success" => false, "message" => "Please fill in all required fields.", "_code" => 400];
    }
    if (empty($invoice)) {
        return ["success" => false, "message" => "Please fill in all required fields.", "_code" => 400];
    }

    // make sure this invoice number hasn't been used before
    $dupChk = $pdo->prepare("SELECT id FROM purchase_returns WHERE UPPER(TRIM(invoice_number)) = ? LIMIT 1");
    $dupChk->execute([$invoice]);
    if ($dupChk->fetch()) {
        return ["success" => false, "message" => "Invoice number already used. Please generate a new one.", "_code" => 409];
    }

    // try to find the matching product in our inventory (exact match first)
    $prodStmt = $pdo->prepare(
        "SELECT id, name FROM products WHERE LOWER(TRIM(name)) = LOWER(?) AND approval_status = 'approved' LIMIT 1"
    );
    $prodStmt->execute([$item]);
    $product = $prodStmt->fetch(PDO::FETCH_ASSOC);

    // if exact match fails, try a partial/substring match as a fallback
    if (!$product) {
        $prodStmt2 = $pdo->prepare(
            "SELECT id, name FROM products WHERE LOWER(name) LIKE ? AND approval_status = 'approved' LIMIT 1"
        );
        $prodStmt2->execute(['%' . strtolower($item) . '%']);
        $product = $prodStmt2->fetch(PDO::FETCH_ASSOC);
    }

    // calculate current stock from the ledger (IN - OUT) for this product
    $currentStock = 0;
    if ($product) {
        $stockStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(CASE WHEN type='IN' THEN quantity ELSE 0 END), 0)
                  - COALESCE(SUM(CASE WHEN type='OUT' THEN quantity ELSE 0 END), 0)
             FROM stock_ledger WHERE product_id = ?"
        );
        $stockStmt->execute([$product['id']]);
        $currentStock = (int)$stockStmt->fetchColumn();
    }

    // can't return more units than we actually have in stock
    if ($product && $qty > $currentStock) {
        return [
            "success" => false,
            "message" => "Return quantity exceeds available stock. Only {$currentStock} unit" . ($currentStock !== 1 ? 's' : '') . " in stock.",
            "_code"   => 422
        ];
    }

    // insert the purchase return record into the database
    $ins = $pdo->prepare(
        "INSERT INTO purchase_returns (invoice_number, supplier, item_name, category, quantity, product_id)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $ins->execute([
        $invoice, $supplier, $item, $category, $qty,
        $product ? $product['id'] : null // product_id is null if we couldn't find the product
    ]);
    $returnId = $pdo->lastInsertId();

    // record the stock movement in the ledger as an OUT transaction
    // this keeps our stock numbers accurate after the return
    if ($product) {
        $ledger = $pdo->prepare(
            "INSERT INTO stock_ledger (product_id, product_name, type, quantity, reference_id, reference_type, note)
             VALUES (?, ?, 'OUT', ?, ?, 'return', ?)"
        );
        $ledger->execute([
            $product['id'], $product['name'], $qty, $returnId,
            "Purchase return {$invoice} – supplier: {$supplier}"
        ]);
    }

    return [
        "success"        => true,
        "message"        => "Purchase return recorded successfully. Stock has been updated.",
        "id"             => $returnId,
        "invoice_number" => $invoice
    ];
}
