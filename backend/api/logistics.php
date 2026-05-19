<?php
require_once __DIR__ . '/../config/helpers.php';   // helper functions load garne
require_once __DIR__ . '/../config/auth.php';      // authentication check garne
require_once __DIR__ . '/../controllers/LogisticsController.php'; // logistics related functions

setCorsHeaders(); // frontend bata API call allow garne (CORS)

$method = $_SERVER['REQUEST_METHOD']; // कुन HTTP method aayo (GET, POST, PUT, DELETE)

switch ($method) {

    case 'GET':
        // sabai logistics/order data fetch garne
        sendResponse(getLogistics());
        break;

    case 'POST':
        // naya logistics/order create garne
        // JSON body bata data lina
        sendResponse(createLogistics(getJsonBody()));
        break;

    case 'PUT':
        // existing logistics/order update garne
        sendResponse(updateLogistics(getJsonBody()));
        break;

    case 'DELETE':
        // logistics/order delete garne
        sendResponse(deleteLogistics(getJsonBody()));
        break;

    default:
        // aru method (PATCH, etc.) allow chaina
        sendResponse([
            'success' => false,
            'message' => 'Method not allowed.'
        ], 405);
}