<?php
// need the database connection ($pdo) and helper functions
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// we're always returning JSON from this file
header('Content-Type: application/json');

// start the session so we can check who's logged in
session_start();

// if there's no user_id in the session, they're not logged in - stop them here
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// read the ?action= parameter from the URL to know what to do
$action = $_GET['action'] ?? '';

// only admins and supervisors are allowed to list or update product approvals
if (in_array($action, ['list', 'update'])) {
    if ($_SESSION['role'] !== 'admin' && $_SESSION['job_role'] !== 'supervisor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

// GET /permissions.php?action=list
// returns all products that are still waiting to be approved or rejected
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $stmt = $pdo->prepare("
        SELECT p.id as product_id, p.name as product_name, p.category, p.created_at, u.name as staff_name, u.username
        FROM products p
        LEFT JOIN users u ON p.added_by = u.id
        WHERE p.approval_status = 'pending'
    ");
    $stmt->execute();
    $requests = $stmt->fetchAll();
    echo json_encode(['success' => true, 'requests' => $requests]);
    exit;
}

// POST /permissions.php?action=update
// lets an admin/supervisor approve or reject a pending product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $data      = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $productId = $data['product_id'] ?? 0;
    $newStatus = $data['status'] ?? '';

    // only 'approved' or 'rejected' are valid - anything else is a mistake
    if (!in_array($newStatus, ['approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }

    // update the product but only if it's still pending (safety check)
    $stmt = $pdo->prepare("UPDATE products SET approval_status = ? WHERE id = ? AND approval_status = 'pending'");
    $stmt->execute([$newStatus, $productId]);
    echo json_encode(['success' => true, 'message' => 'Product status updated.']);
    exit;
}

// POST /permissions.php?action=acknowledge
// staff use this to mark that they've seen the approval/rejection notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'acknowledge') {
    $data      = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $productId = $data['product_id'] ?? 0;

    // we also check added_by = user_id so staff can only acknowledge their OWN products
    $stmt = $pdo->prepare("UPDATE products SET is_notified = 1 WHERE id = ? AND added_by = ?");
    $stmt->execute([$productId, $_SESSION['user_id']]);
    echo json_encode(['success' => true, 'message' => 'Acknowledged.']);
    exit;
}

// if we got here, the action param was something we don't recognise
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
