<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php'; // PHP session integration
require_once __DIR__ . '/../controllers/ProductController.php';

setCorsHeaders();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        sendResponse(getProducts());
        break;
    case 'POST':
        sendResponse(createProduct(getJsonBody()));
        break;
    case 'PUT':
        if (isset($_GET['action']) && $_GET['action'] === 'restock') {
            sendResponse(restockProduct(getJsonBody()));
        } elseif (isset($_GET['action']) && $_GET['action'] === 'status') {
            sendResponse(updateProductStatus(getJsonBody()));
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
