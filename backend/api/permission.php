<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

// Admin actions
if (in_array($action, ['list', 'update'])) {
    if ($_SESSION['role'] !== 'admin' && $_SESSION['job_role'] !== 'supervisor') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    // Admin lists pending products
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    // Admin approves/rejects a product
    $data = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $productId = $data['product_id'] ?? 0;
    $newStatus = $data['status'] ?? ''; // 'approved' or 'rejected'
    
    if (!in_array($newStatus, ['approved', 'rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    $stmt = $pdo->prepare("UPDATE products SET approval_status = ? WHERE id = ? AND approval_status = 'pending'");
    $stmt->execute([$newStatus, $productId]);
    
    echo json_encode(['success' => true, 'message' => 'Product status updated.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'acknowledge') {
    // Staff acknowledges rejection
    $data = json_decode(file_get_contents("php://input"), true) ?: $_POST;
    $productId = $data['product_id'] ?? 0;
    
    $stmt = $pdo->prepare("UPDATE products SET is_notified = 1 WHERE id = ? AND added_by = ?");
    $stmt->execute([$productId, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'message' => 'Acknowledged.']);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
