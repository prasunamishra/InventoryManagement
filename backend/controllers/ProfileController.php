<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// 1. Get current user's profile detailss
function getProfile() {
    global $pdo;

    // session start garxa if already start bhayeko chaina
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // check garxa user login xa ki xaina
    if (!isset($_SESSION['user_id'])) {
        // login xaina vane unauthorized return garxa
        return ["success" => false, "message" => "Unauthorized.", "_code" => 401];
    }

    // session bata user_id linxa
    $userId = $_SESSION['user_id'];

    // database bata user ko basic info (id, username, name, email) fetch garxa
    $stmt = $pdo->prepare("SELECT id, username, name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    // user vetiyena vane error dinxa
    if (!$user) {
        return ["success" => false, "message" => "User not found.", "_code" => 404];
    }

    // sabai thik xa vane profile data return garxa
    return ["success" => true, "profile" => $user];
}

// 2. Update user's name, email, and username in DB and session
function updateProfile($data) {
    global $pdo;

    // session start garxa if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // check login xa ki xaina
    if (!isset($_SESSION['user_id'])) {
        return ["success" => false, "message" => "Unauthorized.", "_code" => 401];
    }

    // session bata user_id linxa
    $userId = $_SESSION['user_id'];

    // input bata data linxa ani trim garxa (extra space hatauna)
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $newUsername = trim($data['username'] ?? '');

    // name ra username empty bhayo vane error dinxa
    if (!$name || !$newUsername) {
        return ["success" => false, "message" => "Name and username are required.", "_code" => 400];
    }

        // Validate proper email format and ensure it ends with .com
    if ($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ["success" => false, "message" => "Please enter a valid email address.", "_code" => 400];
        }
        if (!preg_match('/\.com$/i', $email)) {
            return ["success" => false, "message" => "Email must end with .com", "_code" => 400];
        }
    }


    // check garxa yo username already aru user le use gareko xa ki xaina
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$newUsername, $userId]);

    // same username vetiyo vane error dinxa
    if ($stmt->fetch()) {
        return ["success" => false, "message" => "Username is already taken.", "_code" => 409];
    }

    // old username fetch garxa (yo part actually use bhayeko chaina)
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldUser = $stmt->fetch();
    $oldUsername = $oldUser['username'];

    // database ma user ko data update garxa
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, username = ? WHERE id = ?");
    $stmt->execute([$name, $email, $newUsername, $userId]);

    // session ma pani new username update garxa
    $_SESSION['username'] = $newUsername;

    // success message return garxa
    return [
        "success" => true,
        "message" => "Profile updated successfully.",
        "name" => $name,
        "username" => $newUsername
    ];
}