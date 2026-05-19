<?php
require_once __DIR__ . '/../config/helpers.php';   // helper functions load garne
require_once __DIR__ . '/../config/auth.php';      // user authentication check
require_once __DIR__ . '/../controllers/RequestController.php'; // request related functions

setCorsHeaders(); // frontend bata API call garna allow garne (CORS)

$method = $_SERVER['REQUEST_METHOD']; // कुन HTTP method aayo (GET, POST)
$action = $_GET['action'] ?? '';      // URL bata action lina

// GET REQUEST handle garne
if ($method === 'GET') {

    // यदि action = count vane pending request ko number dinxa
    if ($action === 'count') {
        sendResponse(countPendingRequests());
    } 
    
    // यदि action = list vane request list dinxa (default Pending)
    elseif ($action === 'list') {
        $status = $_GET['status'] ?? 'Pending'; // status filter
        sendResponse(listRequests(['status' => $status]));
    } 
    
    // गलत action aaye vane error
    else {
        sendResponse([
            'success' => false,
            'message' => 'Invalid action.'
        ], 400);
    }
}

//  POST REQUEST handle garne
if ($method === 'POST') {

    $body = getJsonBody(); // JSON body bata data lina
    $postAction = $body['action'] ?? $action; // body bata action ya URL bata

    // यदि action = submit vane naya request create garxa
    if ($postAction === 'submit') {
        sendResponse(submitRequest($body));
    } 
    
    // यदि action = review vane admin/supervisor le approve/reject garxa
    elseif ($postAction === 'review') {
        sendResponse(reviewRequest($body));
    } 
    
    // गलत action aaye vane error
    else {
        sendResponse([
            'success' => false,
            'message' => 'Invalid action.'
        ], 400);
    }
}

//  OTHER METHODS (PUT, DELETE, etc.) aaye vane allow chaina
// GET/POST bahek aru method aaye vane allow chaina
sendResponse([
    'success' => false,
    'message' => 'Method not allowed.'
], 405);