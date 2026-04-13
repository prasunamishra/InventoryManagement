<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php'; // PHP session integration
require_once __DIR__ . '/../controllers/LogisticsController.php';

setCorsHeaders();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        sendResponse(getLogistics());
        break;
    case 'POST':
        sendResponse(createLogistics(getJsonBody()));
        break;
    case 'PUT':
        sendResponse(updateLogistics(getJsonBody()));
        break;
    case 'DELETE':
        requireAdmin();
        sendResponse(deleteLogistics(getJsonBody()));
        break;
    default:
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
