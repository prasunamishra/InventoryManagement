<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

function getProfile() {
    global $pdo;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return ["success" => false, "message" => "Unauthorized.", "_code" => 401];
    }

    $userId = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT id, username, name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ["success" => false, "message" => "User not found.", "_code" => 404];
    }

    return ["success" => true, "profile" => $user];
}

function updateProfile($data) {
    global $pdo;

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return ["success" => false, "message" => "Unauthorized.", "_code" => 401];
    }

    $userId = $_SESSION['user_id'];
    $name = trim($data['name'] ?? '');
    $email = trim($data['email'] ?? '');
    $newUsername = trim($data['username'] ?? '');

    if (!$name || !$newUsername) {
        return ["success" => false, "message" => "Name and username are required.", "_code" => 400];
    }

    // Check if new username is already taken by someone else
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$newUsername, $userId]);
    if ($stmt->fetch()) {
        return ["success" => false, "message" => "Username is already taken.", "_code" => 409];
    }

    // Get old username to update staff table if necessary
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $oldUser = $stmt->fetch();
    $oldUsername = $oldUser['username'];

    // Update users table
    $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, username = ? WHERE id = ?");
    $stmt->execute([$name, $email, $newUsername, $userId]);

    // Update staff table if this user is a staff member
    // The staff table uses the username to link conceptually, but we can update by old username
    $stmt = $pdo->prepare("UPDATE staff SET name = ?, email = ?, username = ? WHERE username = ?");
    $stmt->execute([$name, $email, $newUsername, $oldUsername]);

    // Update session
    $_SESSION['username'] = $newUsername;

    return ["success" => true, "message" => "Profile updated successfully.", "name" => $name, "username" => $newUsername];
}
