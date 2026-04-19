<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../controllers/SupplierController.php';

setCorsHeaders();
$method = $_SERVER['REQUEST_METHOD'];

requireAdminOrSupervisor();

switch ($method) {
    case 'GET':
        sendResponse(getSuppliers());
        break;
    case 'POST':
        sendResponse(createSupplier(getJsonBody()));
        break;
    case 'PUT':
        sendResponse(updateSupplier(getJsonBody()));
        break;
    case 'DELETE':
        sendResponse(deleteSupplier(getJsonBody()));
        break;
    default:
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
