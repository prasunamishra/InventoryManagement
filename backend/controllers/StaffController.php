<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

function getStaff() {
    global $pdo;
    $rows = $pdo->query("SELECT * FROM staff ORDER BY created_at DESC")->fetchAll();
    return ["success" => true, "staff" => $rows];
}

function createStaff($data) {
    global $pdo;
    $name     = trim($data['name']     ?? '');
    $username = trim($data['username'] ?? '');
    $role     = trim($data['role']     ?? 'Floor Staff');
    $password = trim($data['password'] ?? '');
    $phone    = trim($data['phone']    ?? '');

    if (!$name || !$username || !$password || !$phone) {
        return ["success" => false, "message" => "Name, username, phone and password are required.", "_code" => 400];
    }

    if (!preg_match('/^\d{10}$/', $phone)) {
        return ["success" => false, "message" => "Phone number must be exactly 10 digits.", "_code" => 400];
    }

    $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $check->execute([$username]);
    if ($check->fetch()) {
        return ["success" => false, "message" => "Username already exists.", "_code" => 409];
    }

    $maxId = (int) ($pdo->query("SELECT COALESCE(MAX(id),0) FROM staff")->fetchColumn());
    $stfId = 'STF-' . str_pad($maxId + 1, 4, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("INSERT INTO staff (stf_id, name, username, role, phone, plain_password) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$stfId, $name, $username, $role, $phone, $password]);

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt2 = $pdo->prepare("INSERT INTO users (username, password, name, role) VALUES (?, ?, ?, 'staff')");
    $stmt2->execute([$username, $hashedPassword, $name]);

    return ["success" => true, "message" => "Staff member added successfully."];
}

function updateStaff($data) {
    global $pdo;
    $id          = (int) ($data['id']       ?? 0);
    $name        = trim($data['name']       ?? '');
    $newUsername = trim($data['username']   ?? '');
    $role        = trim($data['role']       ?? 'Floor Staff');
    $phone       = trim($data['phone']      ?? '');
    $newPassword = trim($data['password']   ?? '');

    if (!$id || !$name || !$phone || !$newUsername) {
        return ["success" => false, "message" => "ID, name, username and phone are required.", "_code" => 400];
    }

    if (!preg_match('/^\d{10}$/', $phone)) {
        return ["success" => false, "message" => "Phone number must be exactly 10 digits.", "_code" => 400];
    }

    $stmt = $pdo->prepare("SELECT username FROM staff WHERE id = ?");
    $stmt->execute([$id]);
    $staff = $stmt->fetch();

    if (!$staff) {
        return ["success" => false, "message" => "Staff member not found.", "_code" => 404];
    }

    $oldUsername = $staff['username'];

    if ($newUsername !== $oldUsername) {
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$newUsername]);
        if ($check->fetch()) {
            return ["success" => false, "message" => "Username already taken.", "_code" => 409];
        }
    }

    // Update users table (with optional password change)
    if ($newPassword !== '') {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $pdo->prepare("UPDATE users SET username = ?, name = ?, password = ? WHERE username = ?")
            ->execute([$newUsername, $name, $hashedPassword, $oldUsername]);
        $pdo->prepare("UPDATE staff SET name = ?, username = ?, role = ?, phone = ?, plain_password = ? WHERE id = ?")
            ->execute([$name, $newUsername, $role, $phone, $newPassword, $id]);
    } else {
        $pdo->prepare("UPDATE users SET username = ?, name = ? WHERE username = ?")
            ->execute([$newUsername, $name, $oldUsername]);
        $pdo->prepare("UPDATE staff SET name = ?, username = ?, role = ?, phone = ? WHERE id = ?")
            ->execute([$name, $newUsername, $role, $phone, $id]);
    }

    return ["success" => true, "message" => "Staff member updated."];
}

function deleteStaff($data) {
    global $pdo;
    $id = (int) ($data['id'] ?? 0);

    if (!$id) {
        return ["success" => false, "message" => "Staff ID is required.", "_code" => 400];
    }

    $stmt = $pdo->prepare("SELECT username FROM staff WHERE id = ?");
    $stmt->execute([$id]);
    $staff = $stmt->fetch();

    if (!$staff) {
        return ["success" => false, "message" => "Staff member not found.", "_code" => 404];
    }

    $del1 = $pdo->prepare("DELETE FROM users WHERE username = ?");
    $del1->execute([$staff['username']]);

    $del2 = $pdo->prepare("DELETE FROM staff WHERE id = ?");
    $del2->execute([$id]);

    return ["success" => true, "message" => "Staff member deleted."];
}
