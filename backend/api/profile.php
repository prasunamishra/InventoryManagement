<?php
require_once __DIR__ . '/../controllers/ProfileController.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $result = getProfile();
    if (isset($result['_code'])) {
        http_response_code($result['_code']);
    }
    echo json_encode($result);
} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }
    
    $result = updateProfile($input);
    if (isset($result['_code'])) {
        http_response_code($result['_code']);
    }
    echo json_encode($result);
} else {
    http_response_code(405);
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
}
