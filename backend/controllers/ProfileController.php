<?php
require_once __DIR__ . '/../config/db.php';        // database connection
require_once __DIR__ . '/../config/helpers.php';  // helper functions

//  GET PROFILE 
// current logged-in user ko profile lina
function getProfile() {
    global $pdo;

    // session start (if already start bhayeko chaina vane)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // user login chaina vane unauthorized
    if (!isset($_SESSION['user_id'])) {
        return ["success" => false, "message" => "Unauthorized.", "_code" => 401];
    }

    $userId = $_SESSION['user_id'];

    // database bata user detail fetch garne
    $stmt = $pdo->prepare("SELECT id, username, name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // user bhetena vane error
    if (!$user) {
        return ["success" => false, "message" => "User not found.", "_code" => 404];
    }

    // success ma profile return garne
    return ["success" => true, "profile" => $user];
}

//  UPDATE PROFILE 
// user ko name, email, username update garne
function updateProfile($data) {
    global $pdo;

    // session start
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // login check
    if (!isset($_SESSION['user_id'])) {
        return ["success" => false, "message" => "Unauthorized.", "_code" => 401];
    }

    $userId = $_SESSION['user_id'];

    // input data lina
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $newUsername = trim($data['username'] ?? '');

    // validation (name ra username compulsory)
    if (!$name || !$newUsername) {
        return ["success" => false, "message" => "Name and username are required.", "_code" => 400];
    }

    // check: username already use bhako xa ki nai
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$newUsername, $userId]);

    if ($stmt->fetch()) {
        return ["success" => false, "message" => "Username is already taken.", "_code" => 409];
    }

    // old username lina (reference ko lagi)
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldUser = $stmt->fetch();
    $oldUsername = $oldUser['username'];

    // database update garne
    $stmt = $pdo->prepare(
        "UPDATE users SET name = ?, email = ?, username = ? WHERE id = ?"
    );
    $stmt->execute([$name, $email, $newUsername, $userId]);

    // session ma pani update garne
    $_SESSION['username'] = $newUsername;

    // success response
    return [
        "success" => true,
        "message" => "Profile updated successfully.",
        "name" => $name,
        "username" => $newUsername
    ];
}