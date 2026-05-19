<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../controllers/SupplierController.php';

setCorsHeaders();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        sendResponse(getSuppliers());
        break;
    case 'POST':
        // Only Admins and Supervisors are allowed to create or modify suppliers
        if ($_SESSION['role'] !== 'admin' && strtolower($_SESSION['job_role']) !== 'supervisor') {
            sendResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }
        
        $data = getJsonBody();
        
        // We route requests based on an 'action' property. 
        // If it's update_status, we toggle the active/inactive state instead of creating a new supplier.
        if (isset($data['action']) && $data['action'] === 'update_status') {
            sendResponse(updateSupplierStatus($data));
        } else {
            // Otherwise, just create a new supplier!
            sendResponse(createSupplier($data));
        }
        break;
    case 'PUT':
        if ($_SESSION['role'] !== 'admin' && strtolower($_SESSION['job_role']) !== 'supervisor') {
            sendResponse(['success' => false, 'message' => 'Forbidden.'], 403);
        }
        sendResponse(updateSupplier(getJsonBody()));
        break;

    default:
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
