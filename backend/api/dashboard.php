<?php
// helpers has stuff like sendResponse() and setCorsHeaders() - we use it everywhere
require_once __DIR__ . '/../config/helpers.php';

// auth.php checks if the user is logged in, if not it kicks them out automatically
require_once __DIR__ . '/../config/auth.php';

// the actual dashboard logic lives here
require_once __DIR__ . '/../controllers/DashboardController.php';

// set the response headers so the frontend can talk to us
setCorsHeaders();

// dashboard is read-only so we only allow GET requests here
requireMethod('GET');

// grab all the dashboard data and send it back as JSON
sendResponse(getDashboardData());
