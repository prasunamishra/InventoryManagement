<?php
/**
 * Authentication Guard
 * Secures APIs by validating the PHP session.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verify that the user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized access. Please log in."]);
    exit();
}

/**
 * Halts execution if the current user is not an admin.
 */
function requireAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Forbidden. Administrator access required."]);
        exit();
    }
}

/**
 * Halts execution if the current user is neither an admin nor a supervisor.
 */
function requireAdminOrSupervisor() {
    $hasAccess = (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') || 
                 (isset($_SESSION['job_role']) && $_SESSION['job_role'] === 'Supervisor');
    
    if (!$hasAccess) {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Forbidden. Administrator or Supervisor access required."]);
        exit();
    }
}
