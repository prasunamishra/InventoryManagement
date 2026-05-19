<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php'; // PHP session integration
require_once __DIR__ . '/../controllers/ProductController.php';

setCorsHeaders();
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? trim($_GET['action']) : (isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '');

switch ($method) {
    case 'GET':
        sendResponse(getProducts());
        break;
    case 'POST':
        sendResponse(createProduct(getJsonBody()));
        break;
    case 'PUT':
        // Support action-based routing for PUT (status change, restock, or full update)
        if ($action === 'status') {
            sendResponse(updateProductStatus(getJsonBody()));
        } elseif ($action === 'restock') {
            sendResponse(restockProduct(getJsonBody()));
        } else {
            sendResponse(updateProduct(getJsonBody()));
        }
        break;
    case 'DELETE':
        sendResponse(deleteProduct(getJsonBody()));
        break;
    default:
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
