<?php
require_once __DIR__ . '/../controllers/PasswordResetController.php'; 
// password reset related functions (forgot, verify, reset)

header("Content-Type: application/json"); 
// response JSON format ma pathaune

$method = $_SERVER['REQUEST_METHOD']; 
// कुन HTTP method aayo check (POST expected)

//  POST REQUEST handle garne
if ($method === 'POST') {

    // JSON body bata data lina, natra normal POST use garne
    $input  = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $action = $input['action'] ?? ''; 
    // कुन action ho (forgot / verify_token / reset)

    //  FORGOT PASSWORD 
    if ($action === 'forgot') {

        $email = trim($input['email'] ?? ''); // user email
        $role  = trim($input['role']  ?? 'admin'); // default admin

        // role valid xa ki check
        if (!in_array($role, ['admin', 'staff'])) {
            $role = 'admin';
        }

        // email empty xa vane error
        if (empty($email)) {
            echo json_encode([
                "success" => false,
                "message" => "Email is required."
            ]);
            exit;
        }

        // reset link send garne
        $result = sendResetLink($email, $role);
        echo json_encode($result);
    } 
    
    //  VERIFY TOKEN 
    elseif ($action === 'verify_token') {

        $token = trim($input['token'] ?? '');

        // token chainxa
        if (empty($token)) {
            echo json_encode([
                "success" => false,
                "message" => "Token is required."
            ]);
            exit;
        }

        // token valid xa ki check
        $result = verifyToken($token);
        echo json_encode($result);
    } 
    
    //  RESET PASSWORD
    elseif ($action === 'reset') {

        $token = trim($input['token'] ?? '');
        $password = trim($input['password'] ?? '');

        // token ra password duita chainxa
        if (empty($token) || empty($password)) {
            echo json_encode([
                "success" => false,
                "message" => "Token and new password are required."
            ]);
            exit;
        }

        // password reset garne
        $result = resetPassword($token, $password);
        echo json_encode($result);
    } 
    
    // -INVALID ACTION AAYE VANE ERROR
    else {
        echo json_encode([
            "success" => false,
            "message" => "Invalid action."
        ]);
    }
} 

// OTHER METHODS (GET, PUT, DELETE, etc.) allow chaina
else {
    // POST bahek aru method allow chaina
    echo json_encode([
        "success" => false,
        "message" => "Method not allowed."
    ]);
}