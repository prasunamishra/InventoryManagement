<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../controllers/PurchaseReturnController.php';

setCorsHeaders();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['action']) && $_GET['action'] === 'next_invoice') {
            sendResponse(getNextPRInvoice());
        } else {
            sendResponse(getPurchaseReturns());
        }
        break;
    case 'POST':
        sendResponse(createPurchaseReturn(getJsonBody()));
        break;
    default:
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
