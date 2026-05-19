<?php
require_once __DIR__ . '/../controllers/ProfileController.php'; 
// profile related functions (get/update)

header("Content-Type: application/json"); 
// response JSON format ma pathaune

$method = $_SERVER['REQUEST_METHOD']; 
// कुन HTTP method aayo (GET, PUT)

// GET PROFILE 
if ($method === 'GET') {

    // user ko profile data fetch garne
    $result = getProfile();

    // यदि error code xa vane set garne (like 401, 404)
    if (isset($result['_code'])) {
        http_response_code($result['_code']);
    }

    // result JSON ma pathaune
    echo json_encode($result);
} 

//  UPDATE PROFILE 
elseif ($method === 'PUT') {

    // JSON body bata data lina
    $input = json_decode(file_get_contents('php://input'), true);

    // यदि JSON empty xa vane normal POST data use garne
    if (!$input) {
        $input = $_POST;
    }

    // profile update garne
    $result = updateProfile($input);

    // यदि error code xa vane set garne
    if (isset($result['_code'])) {
        http_response_code($result['_code']);
    }

    // result JSON ma pathaune
    echo json_encode($result);
} 

// OTHER METHODS 
else {
    // GET ra PUT bahek aru method allow chaina
    http_response_code(405);

    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
}