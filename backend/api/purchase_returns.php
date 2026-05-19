<?php
// helpers for sending responses and reading request bodies
require_once __DIR__ . '/../config/helpers.php';

// auth.php will auto-block anyone not logged in before we do anything else
require_once __DIR__ . '/../config/auth.php';

// all the actual purchase return logic is in this controller
require_once __DIR__ . '/../controllers/PurchaseReturnController.php';

// set the right headers so the frontend can call this
setCorsHeaders();

// figure out what HTTP method was used (GET, POST, etc.)
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // if ?action=next_invoice is in the URL, just return the next invoice number
        // otherwise return the full list of purchase returns
        if (isset($_GET['action']) && $_GET['action'] === 'next_invoice') {
            sendResponse(getNextPRInvoice());
        } else {
            sendResponse(getPurchaseReturns());
        }
        break;

    case 'POST':
        // creating a new purchase return - pass the JSON body to the controller
        sendResponse(createPurchaseReturn(getJsonBody()));
        break;

    default:
        // PUT and DELETE aren't supported here, so send a 405
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
