<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php'; // PHP session integration
require_once __DIR__ . '/../controllers/StaffController.php';

setCorsHeaders();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        sendResponse(getStaff());
        break;
    case 'POST':
        requireAdmin();
        sendResponse(createStaff(getJsonBody()));
        break;
    case 'PUT':
        requireAdmin();
        sendResponse(updateStaff(getJsonBody()));
        break;
    case 'DELETE':
        requireAdmin();
        sendResponse(deleteStaff(getJsonBody()));
        break;
    default:
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
