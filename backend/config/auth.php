<?php
/**
 * auth.php — the "bouncer" for all our protected API endpoints
 *
 * Just require_once this file at the top of any endpoint that needs
 * a logged-in user, and it'll handle the rest automatically.
 */

// start the session if it hasn't been started yet
// (some files start it themselves, so we check first to avoid errors)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// the bare minimum to be "logged in" is having a user_id AND a username in the session
// if either is missing, we stop the request right here with a 401
if (!isset($_SESSION['user_id']) || !isset($_SESSION['username'])) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized access. Please log in."]);
    exit();
}

/**
 * Call this function at the top of any endpoint that ONLY admins should access.
 * It will kill the request with a 403 if the user isn't an admin.
 */
function requireAdmin() {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Forbidden. Administrator access required."]);
        exit();
    }
}

/**
 * Call this for endpoints that both admins AND supervisors can use.
 * Regular staff will get a 403 if they try to hit these endpoints.
 *
 * Note: the job_role check is case-sensitive here ("Supervisor" with capital S)
 * so make sure that's consistent in the database.
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
