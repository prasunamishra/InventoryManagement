<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php'; // ensure logged in
require_once __DIR__ . '/../controllers/StaffController.php';

setCorsHeaders();

// Only Admins and Supervisors can view staff
$role = $_SESSION['role'] ?? '';
$jobRole = $_SESSION['job_role'] ?? '';
if ($role !== 'admin' && strtolower($jobRole) !== 'supervisor') {
    sendResponse(['success' => false, 'message' => 'Unauthorized. Access required.'], 403);
}

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            sendResponse(getStaffById($_GET['id']));
        } else {
            sendResponse(getStaffList());
        }
        break;
    case 'POST':
        if ($role !== 'admin') sendResponse(['success' => false, 'message' => 'Admin only.'], 403);
        $data = getJsonBody();
        if (isset($data['action']) && $data['action'] === 'update_status') {
            sendResponse(updateStaffStatus($data));
        } else {
            sendResponse(addStaff($data));
        }
        break;
    case 'PUT':
        if ($role !== 'admin') sendResponse(['success' => false, 'message' => 'Admin only.'], 403);
        sendResponse(updateStaff(getJsonBody()));
        break;
    default:
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
