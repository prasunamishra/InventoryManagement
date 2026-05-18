<?php
/**
 * Yo file le request approval system manage garxa (RBAC system)
 * - Staff (Logistics / Inventory) le request pathaune
 * - Admin / Supervisor le approve ya reject garne
 */

require_once __DIR__ . '/../config/db.php';        // database connection
require_once __DIR__ . '/../config/helpers.php';  // helper functions

//  USER INFO 
// current logged-in user ko info lina
function getCurrentUserInfo()
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start(); // session start
    }

    return [
        'user_id' => $_SESSION['user_id'] ?? 0,
        'name' => $_SESSION['name'] ?? ($_SESSION['username'] ?? 'Unknown'),
        'role' => $_SESSION['role'] ?? '',        // admin | staff
        'job_role' => $_SESSION['job_role'] ?? '', // Supervisor | Logistics Coordinator ...
    ];
}

// ROLE CHECK 
// user approver ho ki haina check (admin ya supervisor matra)
function callerIsApprover(array $user): bool
{
    return $user['role'] === 'admin'
        || strtolower($user['job_role']) === 'supervisor';
}

// SUBMIT REQUEST 
// staff le naya request pathaune
function submitRequest(array $data): array
{
    global $pdo;
    $user = getCurrentUserInfo();

    // login navaye error
    if (!$user['user_id']) {
        return ['success' => false, 'message' => 'Unauthorized.', '_code' => 401];
    }

    $actionType = trim($data['action_type'] ?? '');
    $payload = $data['payload'] ?? [];
    $description = trim($data['description'] ?? '');

    // valid action haru
    $validActions = [
        'create_order',
        'update_order',
        'delete_order',
        'create_product',
        'update_product',
        'delete_product',
        'update_product_status',
        'stock_adjustment',
        'create_purchase_return'
    ];

    // invalid action type
    if (!in_array($actionType, $validActions)) {
        return ['success' => false, 'message' => 'Invalid action type.', '_code' => 400];
    }

    // description chainxa
    if (!$description) {
        return ['success' => false, 'message' => 'Description is required.', '_code' => 400];
    }

    // request database ma save (Pending status)
    $stmt = $pdo->prepare(
        "INSERT INTO pending_requests
            (requested_by, requester_name, requester_role, action_type, payload, description, status)
         VALUES (?, ?, ?, ?, ?, ?, 'Pending')"
    );

    $stmt->execute([
        $user['user_id'],
        $user['name'],
        $user['job_role'] ?: $user['role'],
        $actionType,
        json_encode($payload), // payload JSON ma save
        $description,
    ]);

    return [
        'success' => true,
        'message' => 'Your request has been submitted for approval.',
        'request_id' => (int) $pdo->lastInsertId(),
        'pending' => true,
    ];
}

//  LIST REQUESTS 
// staff le afno matra dekhxa, admin le sabai
function listRequests(array $filters = []): array
{
    global $pdo;
    $user = getCurrentUserInfo();
    $isApprover = callerIsApprover($user);

    $status = $filters['status'] ?? 'Pending';

    // allowed status
    $validStatuses = ['Pending', 'Approved', 'Rejected', 'all'];
    if (!in_array($status, $validStatuses)) {
        $status = 'Pending';
    }

    $where = [];
    $params = [];

    // staff ho vane afno matra
    if (!$isApprover) {
        $where[] = "pr.requested_by = ?";
        $params[] = $user['user_id'];
    }

    // specific status filter
    if ($status !== 'all') {
        $where[] = "pr.status = ?";
        $params[] = $status;
    }

    $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

    // request list fetch
    $stmt = $pdo->prepare(
        "SELECT pr.*, u.name as reviewer_name
         FROM pending_requests pr
         LEFT JOIN users u ON u.id = pr.reviewed_by
         $whereSql
         ORDER BY pr.created_at DESC"
    );

    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // payload JSON decode garne
    foreach ($rows as &$row) {
        $row['payload'] = json_decode($row['payload'], true);
    }
    unset($row);

    return ['success' => true, 'requests' => $rows];
}

//REVIEW REQUEST 
// admin/supervisor le approve ya reject garne
function reviewRequest(array $data): array
{
    global $pdo;
    $user = getCurrentUserInfo();

    // approver navaye access deny
    if (!callerIsApprover($user)) {
        return ['success' => false, 'message' => 'Forbidden.', '_code' => 403];
    }

    $requestId = (int) ($data['request_id'] ?? 0);
    $decision = trim($data['decision'] ?? ''); // Approved | Rejected

    if (!$requestId || !in_array($decision, ['Approved', 'Rejected'])) {
        return ['success' => false, 'message' => 'Request ID and valid decision are required.', '_code' => 400];
    }

    // pending request fetch
    $stmt = $pdo->prepare("SELECT * FROM pending_requests WHERE id = ? AND status = 'Pending' LIMIT 1");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        return ['success' => false, 'message' => 'Request not found or already reviewed.', '_code' => 404];
    }

    $pdo->beginTransaction(); // transaction start

    try {
        // status update garne
        $pdo->prepare(
            "UPDATE pending_requests
             SET status = ?, reviewed_by = ?, reviewed_at = NOW()
             WHERE id = ?"
        )->execute([$decision, $user['user_id'], $requestId]);

        // approve vaye actual action apply garne
        if ($decision === 'Approved') {
            $payload = json_decode($request['payload'], true) ?? [];
            $actionType = $request['action_type'];

            $result = applyApprovedAction($actionType, $payload, $pdo);

            // fail vaye rollback
            if (!$result['success']) {
                $pdo->rollBack();
                return ['success' => false, 'message' => 'Approval failed: ' . $result['message'], '_code' => 500];
            }
        }

        $pdo->commit(); // success vaye save
        return ['success' => true, 'message' => "Request {$decision} successfully."];

    } catch (Exception $e) {
        $pdo->rollBack(); // error vaye undo
        return ['success' => false, 'message' => 'Server error: ' . $e->getMessage(), '_code' => 500];
    }
}

// APPLY ACTION 
// approve paxi actual kaam garne
function applyApprovedAction(string $actionType, array $payload, $pdo): array
{
    switch ($actionType) {

        // ORDER ACTIONS
        case 'create_order':
        case 'update_order':
        case 'delete_order':
            require_once __DIR__ . '/LogisticsController.php';
            return $actionType === 'create_order' ? createLogistics($payload)
                : ($actionType === 'update_order' ? updateLogistics($payload) : deleteLogistics($payload));

        // PRODUCT ACTIONS
        case 'create_product':
            require_once __DIR__ . '/ProductController.php';
            $payload['_bypass_approval'] = true; // direct approved
            return createProduct($payload);

        case 'update_product':
        case 'update_product_status':
        case 'delete_product':
            require_once __DIR__ . '/ProductController.php';
            return $actionType === 'update_product' ? updateProduct($payload)
                : ($actionType === 'update_product_status' ? updateProductStatus($payload) : deleteProduct($payload));

        // PURCHASE RETURN
        case 'create_purchase_return':
            require_once __DIR__ . '/PurchaseReturnController.php';
            return createPurchaseReturn($payload);

        // STOCK ADJUSTMENT
        case 'stock_adjustment':
            return applyStockAdjustment($payload, $pdo);

        default:
            return ['success' => false, 'message' => 'Unknown action type.'];
    }
}

//  STOCK ADJUSTMENT 
function applyStockAdjustment(array $payload, $pdo): array
{
    $productId = (int) ($payload['product_id'] ?? 0);
    $type = trim($payload['type'] ?? ''); // IN | OUT
    $quantity = (int) ($payload['quantity'] ?? 0);
    $note = trim($payload['note'] ?? 'Manual adjustment');

    if (!$productId || !in_array($type, ['IN', 'OUT']) || $quantity <= 0) {
        return ['success' => false, 'message' => 'Invalid stock adjustment payload.'];
    }

    // product name fetch
    $prodStmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
    $prodStmt->execute([$productId]);
    $prodName = $prodStmt->fetchColumn();

    if (!$prodName) {
        return ['success' => false, 'message' => 'Product not found.'];
    }

    // ledger ma entry halne
    $pdo->prepare(
        "INSERT INTO stock_ledger (product_id, product_name, type, quantity, reference_type, note)
         VALUES (?, ?, ?, ?, 'adjustment', ?)"
    )->execute([$productId, $prodName, $type, $quantity, $note]);

    return ['success' => true, 'message' => 'Stock adjusted.'];
}

//  COUNT PENDING 
// dashboard ma pending request count dekhauna
function countPendingRequests(): array
{
    global $pdo;
    $user = getCurrentUserInfo();

    // staff lai count 0 dekhaune
    if (!callerIsApprover($user)) {
        return ['success' => true, 'count' => 0];
    }

    $count = (int) $pdo->query("SELECT COUNT(*) FROM pending_requests WHERE status = 'Pending'")->fetchColumn();
    return ['success' => true, 'count' => $count];
}