<?php
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../controllers/AuthController.php';

setCorsHeaders();
requireMethod('POST');

sendResponse(handleLogout());