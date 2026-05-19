<?php
require_once __DIR__ . '/../controllers/PasswordResetController.php';

header("Content-Type: application/json");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // Read JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Fallback to $_POST if not JSON
    if (!$input) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    if ($action === 'forgot') {
        $email = trim($input['email'] ?? '');
        $role  = trim($input['role']  ?? 'admin');

        // Validate role to prevent injection
        if (!in_array($role, ['admin', 'staff'])) {
            $role = 'admin';
        }

        if (empty($email)) {
            echo json_encode(["success" => false, "message" => "Email is required."]);
            exit;
        }
        $result = sendResetLink($email, $role);
        echo json_encode($result);
        
    } elseif ($action === 'verify_token') {
        $token = trim($input['token'] ?? '');
        if (empty($token)) {
            echo json_encode(["success" => false, "message" => "Token is required."]);
            exit;
        }
        $result = verifyToken($token);
        echo json_encode($result);
        
    } elseif ($action === 'reset') {
        $token = trim($input['token'] ?? '');
        $password = trim($input['password'] ?? '');
        
        if (empty($token) || empty($password)) {
            echo json_encode(["success" => false, "message" => "Token and new password are required."]);
            exit;
        }
        $result = resetPassword($token, $password);
        echo json_encode($result);
        
    } else {
        echo json_encode(["success" => false, "message" => "Invalid action."]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Method not allowed."]);
}
