<?php
require_once __DIR__ . '/../config/helpers.php';      // helper functions load garne
require_once __DIR__ . '/../controllers/AuthController.php'; // auth related functions (login/logout)

// CORS allow garne (frontend bata request aauna dinxa)
setCorsHeaders();

// POST method matra allow garne (GET aaye error hunxa)
requireMethod('POST');

// logout process handle garne (session destroy, user logout)
$result = handleLogout();

// result (success/fail) client lai pathaune
sendResponse($result);