<?php
require_once __DIR__ . '/../config/helpers.php';   // helper functions load garne
require_once __DIR__ . '/../config/auth.php';      // authentication check garne
require_once __DIR__ . '/../controllers/ProductController.php'; // product related functions

setCorsHeaders(); // frontend bata API call allow garne (CORS)

$method = $_SERVER['REQUEST_METHOD']; // कुन HTTP method aayo (GET, POST, PUT, DELETE)

switch ($method) {

    case 'GET':
        // sabai product list fetch garne
        sendResponse(getProducts());
        break;

    case 'POST':
        // JSON body bata data lina
        $body   = getJsonBody();

        // कुन action ho decide garne (default create)
        $action = $body['action'] ?? 'create';

        // product ko status update (active/inactive)
        if ($action === 'update_status') {
            sendResponse(updateProductStatus($body));
        } 
        
        // admin le product approve/reject garne
        elseif ($action === 'approve') {
            sendResponse(updateApprovalStatus($body));
        } 
        
        // default case: naya product create garne
        else {
            sendResponse(createProduct($body));
        }
        break;

    case 'PUT':
        // existing product update garne
        sendResponse(updateProduct(getJsonBody()));
        break;

    case 'DELETE':
        // product delete garne
        sendResponse(deleteProduct(getJsonBody()));
        break;

    default:
        // aru method allow chaina
        sendResponse([
            'success' => false,
            'message' => 'Method not allowed.'
        ], 405);
}