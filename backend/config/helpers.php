<?php
/**
 * helpers.php — a bunch of small utility functions we use in basically every file
 *
 * Rather than copy-pasting the same header/response/body-reading code
 * into every single endpoint, we put it all here and just require_once this file.
 */

// 1. Set CORS headers and handle preflight requests
//    Every API endpoint calls this first so the browser doesn't complain
function setCorsHeaders() {
    header("Content-Type: application/json");

    // allow any origin for now (fine for local dev, might want to restrict in production)
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type");

    // browsers send a "preflight" OPTIONS request before POST/PUT/DELETE
    // we respond with 204 (No Content) and exit so nothing else runs
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit();
    }
}

// 2. Send a JSON response and stop execution
//    We always call this instead of echo + exit so everything is consistent
//    $httpCode defaults to 200 but you can pass 400, 403, 404, etc.
function sendResponse($payload, $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode($payload);
    exit();
}

// 3. Read and parse the JSON body from the incoming request
//    Most POST/PUT requests send data as JSON in the request body,
//    this just grabs it and converts it to a PHP array for us
function getJsonBody() {
    $data = json_decode(file_get_contents('php://input'), true);

    // if the body was empty or malformed JSON, just return an empty array
    // so the rest of the code doesn't crash trying to access array keys
    return is_array($data) ? $data : [];
}

// 4. Enforce that only a specific HTTP method is allowed on an endpoint
//    e.g. requireMethod('GET') will block POST, PUT, DELETE, etc.
function requireMethod($method) {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
}

// 5. Check if a password meets our security requirements
//    Returns an error message string if it fails, or null if it's fine
//    We use this when adding or updating staff passwords
function validateStrongPassword(string $password): ?string {
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }
    // needs at least one capital letter
    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter (A–Z).';
    }
    // needs at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter (a–z).';
    }
    // needs at least one digit
    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number (0–9).';
    }
    // needs at least one special character (anything that's not a letter or digit)
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must contain at least one special character (e.g., @, #, $, !).';
    }

    // password passed all checks - return null to signal "no error"
    return null;
}
