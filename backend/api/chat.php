<?php
// start the session so we can remember who the user is across requests
session_start();

// tell the browser we're sending back JSON, not HTML
header("Content-Type: application/json");

// CORS stuff - basically lets the frontend talk to this file
// even if they're on a different port/domain (super common in local dev)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// browsers send a "preflight" OPTIONS request before the real one
// we just say "ok cool" and stop here so it doesn't crash
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// bring in the ChatController so we can actually do something with the message
require_once __DIR__ . '/../controllers/ChatController.php';

// grab the JSON body the frontend sent us and pull out the message
$data = json_decode(file_get_contents("php://input"), true);
$message = isset($data['message']) ? trim($data['message']) : '';

// don't do anything if the message is empty - just send back a nudge
if (empty($message)) {
    echo json_encode(['reply' => 'Please provide a message.']);
    exit;
}

// pass the message to the controller and send back whatever it returns
$response = ChatController::handleMessage($message);
echo json_encode($response);
