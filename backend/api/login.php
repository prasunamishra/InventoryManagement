<?php
// we need helpers for sendResponse() and requireMethod()
require_once __DIR__ . '/../config/helpers.php';

// AuthController has the handleLogin() function that does the actual login logic
require_once __DIR__ . '/../controllers/AuthController.php';

// set CORS headers so the frontend can reach this endpoint
setCorsHeaders();

// login only makes sense as a POST request (you're submitting credentials)
// if someone tries GET, this will block them with a 405 error
requireMethod('POST');

// getJsonBody() reads the raw JSON the frontend sent,
// then we pass it straight to handleLogin() and send back the result
sendResponse(handleLogin(getJsonBody()));
