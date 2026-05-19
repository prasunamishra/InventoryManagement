<?php
// pull in helper functions like sendResponse()
require_once __DIR__ . '/../config/helpers.php';

// auth.php silently kicks out anyone who isn't logged in
require_once __DIR__ . '/../config/auth.php';

// the controller that does all the actual staff CRUD work
require_once __DIR__ . '/../controllers/StaffController.php';

setCorsHeaders();

// grab the current user's role and job role from the session
$role = $_SESSION['role'] ?? '';
$jobRole = $_SESSION['job_role'] ?? '';

// staff members can't view the staff list - only admins and supervisors can
if ($role !== 'admin' && strtolower($jobRole) !== 'supervisor') {
    sendResponse(['success' => false, 'message' => 'Unauthorized. Access required.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        // if an ?id= is in the URL, return just that one staff member
        // otherwise return everybody
        if (isset($_GET['id'])) {
            sendResponse(getStaffById($_GET['id']));
        } else {
            sendResponse(getStaffList());
        }
        break;

    case 'POST':
        // only admins can add new staff (supervisors can VIEW but not add)
        if ($role !== 'admin') sendResponse(['success' => false, 'message' => 'Admin only.'], 403);
        sendResponse(addStaff(getJsonBody()));
        break;

    case 'PUT':
        // same deal - editing staff info is admin-only
        if ($role !== 'admin') sendResponse(['success' => false, 'message' => 'Admin only.'], 403);
        sendResponse(updateStaff(getJsonBody()));
        break;

    case 'DELETE':
        // deleting is also admin-only, obviously
        if ($role !== 'admin') sendResponse(['success' => false, 'message' => 'Admin only.'], 403);
        sendResponse(deleteStaff(getJsonBody()));
        break;

    default:
        // anything else (PATCH, HEAD, etc.) just gets rejected
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
