<?php
// bring in our helper and auth utilities
require_once __DIR__ . '/../config/helpers.php';

// this will stop the request dead if the user isn't logged in
require_once __DIR__ . '/../config/auth.php';

// the controller handles all the DB work for suppliers
require_once __DIR__ . '/../controllers/SupplierController.php';

setCorsHeaders();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // anyone logged in can view the suppliers list - no extra role check needed
        sendResponse(getSuppliers());
        break;

    case 'POST':
        // adding a new supplier is restricted to admins and supervisors
        if ($_SESSION['role'] !== 'admin' && strtolower($_SESSION['job_role']) !== 'supervisor') {
            sendResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }
        sendResponse(createSupplier(getJsonBody()));
        break;

    case 'PUT':
        // editing an existing supplier - same restriction as creating
        if ($_SESSION['role'] !== 'admin' && strtolower($_SESSION['job_role']) !== 'supervisor') {
            sendResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }
        sendResponse(updateSupplier(getJsonBody()));
        break;

    case 'DELETE':
        // deleting a supplier - also admin/supervisor only
        if ($_SESSION['role'] !== 'admin' && strtolower($_SESSION['job_role']) !== 'supervisor') {
            sendResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }
        sendResponse(deleteSupplier(getJsonBody()));
        break;

    default:
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
