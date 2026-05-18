<?php
require_once __DIR__ . '/../config/helpers.php';   // helper functions load garne
require_once __DIR__ . '/../config/auth.php';      // authentication check ko lagi
require_once __DIR__ . '/../controllers/StockController.php'; // stock related functions

setCorsHeaders(); // CORS allow garna (frontend bata API call garna milos)

$method = $_SERVER['REQUEST_METHOD']; // kun HTTP method (GET, POST, etc.) aayo check garne

switch ($method) {

    case 'GET':
        // URL bata product_id lina (xa vane)
        $productId = isset($_GET['product_id']) ? (int) $_GET['product_id'] : null;

        // कुन action cha (default = stock)
        $action = $_GET['action'] ?? 'stock';

        // यदि action = ledger vane stock movement dekhauxa
        if ($action === 'ledger') {
            $filters = [
                'product_id' => $productId,             // specific product filter
                'type' => $_GET['type'] ?? null,        // IN ya OUT filter
                'date_from' => $_GET['date_from'] ?? null, // start date
                'date_to' => $_GET['date_to'] ?? null,     // end date
            ];
            sendResponse(getStockLedger($filters)); // ledger data return
        }

        // यदि action = product ra product_id xa vane single product ko stock
        elseif ($action === 'product' && $productId) {
            sendResponse(getProductStock($productId));
        }

        // default case: sabai product ko current stock
        else {
            sendResponse(getCurrentStock($productId));
        }
        break;

    case 'POST':
        // POST request disable xa (manual stock change allow chaina)
        sendResponse([
            'success' => false,
            'message' => 'Manual adjustments are disabled.'
        ], 403);
        break;

    default:
        // aru method (PUT, DELETE, etc.) allow chaina
        sendResponse([
            'success' => false,
            'message' => 'Method not allowed.'
        ], 405);
}