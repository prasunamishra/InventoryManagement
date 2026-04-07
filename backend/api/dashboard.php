<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/auth.php'; // Ensures valid PHP session
require_once __DIR__ . '/../controllers/DashboardController.php';

setCorsHeaders();
requireMethod('GET');

sendResponse(getDashboardData());
