<?php
/**
 * Core Helper Functions
 * Standardises CORS, responses, and requests across all endpoints
 */

function setCorsHeaders() {
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit();
    }
}

function sendResponse($payload, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($payload);
    exit();
}

function getJsonBody() {
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    return is_array($data) ? $data : [];
}

function requireMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
}
        