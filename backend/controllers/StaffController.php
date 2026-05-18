<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// returns all users with role = 'staff', newest first
// we don't select the password column here - no need to expose it
function getStaffList() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, name, username, email, job_role, created_at FROM users WHERE role = 'staff' ORDER BY created_at DESC");
    return ["success" => true, "staff" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

// get a single staff member by their ID
// the AND role = 'staff' check makes sure admins can't be fetched through this endpoint
function getStaffById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, username, email, job_role FROM users WHERE id = ? AND role = 'staff'");
    $stmt->execute([$id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) return ["success" => false, "message" => "Staff member not found."];
    return ["success" => true, "staff" => $staff];
}

// creates a new staff account
function addStaff($data) {
    global $pdo;
    $name     = trim($data['name']     ?? '');
    $username = trim($data['username'] ?? '');
    $email    = trim($data['email']    ?? '');
    $password = trim($data['password'] ?? '');
    $job_role = trim($data['job_role'] ?? 'Inventory Manager'); // default job role if not provided

    // nobody should be able to create an admin account through this endpoint
    // this is a safety check in case someone sends a crafted request
    if (strtolower($job_role) === 'admin') {
        return ["success" => false, "message" => "Cannot assign Admin role.", "_code" => 403];
    }

    // all four fields are required
    if (!$name || !$username || !$password || !$email) {
        return ["success" => false, "message" => "Name, username, email, and password are required.", "_code" => 400];
    }
    
    // run the password through our strength checker from helpers.php
    $pwError = validateStrongPassword($password);
    if ($pwError) {
        return ["success" => false, "message" => $pwError, "_code" => 400];
    }
    
    // usernames must be unique across ALL users (not just staff)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ["success" => false, "message" => "Username is already taken.", "_code" => 409];
    }
    
    // always hash passwords before storing - never store plain text
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // insert the new staff member (role is always 'staff' here)
    $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password, role, job_role) VALUES (?, ?, ?, ?, 'staff', ?)");
    $stmt->execute([$name, $username, $email, $hashedPassword, $job_role]);
    
    return ["success" => true, "message" => "Staff member added successfully."];
}

// updates an existing staff member's info
function updateStaff($data) {
    global $pdo;
    $id       = intval($data['id']     ?? 0);
    $name     = trim($data['name']     ?? '');
    $username = trim($data['username'] ?? '');
    $email    = trim($data['email']    ?? '');
    $job_role = trim($data['job_role'] ?? '');
    $password = trim($data['password'] ?? ''); // password is optional on update

    // same protection as addStaff - can't promote someone to admin through this endpoint
    if (strtolower($job_role) === 'admin') {
        return ["success" => false, "message" => "Cannot assign Admin role.", "_code" => 403];
    }

    if (!$id || !$name || !$username || !$email) {
        return ["success" => false, "message" => "Missing required fields.", "_code" => 400];
    }
    
    // check username uniqueness but exclude the current user (they can keep their own username)
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $id]);
    if ($stmt->fetch()) {
        return ["success" => false, "message" => "Username is already taken.", "_code" => 409];
    }
    
    if ($password) {
        // if a new password was provided, validate and hash it before saving
        $pwError = validateStrongPassword($password);
        if ($pwError) {
            return ["success" => false, "message" => $pwError, "_code" => 400];
        }
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, password = ?, job_role = ? WHERE id = ? AND role = 'staff'");
        $stmt->execute([$name, $username, $email, $hashedPassword, $job_role, $id]);
    } else {
        // no new password - update everything else and leave the password alone
        $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, job_role = ? WHERE id = ? AND role = 'staff'");
        $stmt->execute([$name, $username, $email, $job_role, $id]);
    }
    
    return ["success" => true, "message" => "Staff member updated successfully."];
}

// permanently deletes a staff member by ID
// the AND role = 'staff' clause is a safety net so this can't accidentally delete an admin
function deleteStaff($data) {
    global $pdo;
    $id = intval($data['id'] ?? 0);
    
    if (!$id) return ["success" => false, "message" => "Invalid ID.", "_code" => 400];
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'staff'");
    $stmt->execute([$id]);
    
    return ["success" => true, "message" => "Staff member removed."];
}