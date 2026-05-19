<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

// Get all staff members (users where role = 'staff')
function getStaffList() {
    global $pdo;
    $stmt = $pdo->query("SELECT id, name, username, email, phone, job_role, created_at FROM users ORDER BY created_at DESC");
    return ["success" => true, "staff" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
}

// Get a single staff member by ID
function getStaffById($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, name, username, email, phone, job_role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$staff) return ["success" => false, "message" => "Staff member not found."];
    return ["success" => true, "staff" => $staff];
}

// Add a new staff member
function addStaff($data) {
    global $pdo;
    $name = trim($data['name'] ?? '');
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $password = trim($data['password'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $job_role = trim($data['job_role'] ?? 'Inventory Manager');
    
    if (strtolower($job_role) === 'admin') {
        return ["success" => false, "message" => "Cannot assign Admin role.", "_code" => 403];
    }

    if (!$name || !$username || !$password || !$email || !$phone) {
        return ["success" => false, "message" => "Name, username, email, phone, and password are required.", "_code" => 400];
    }
    
    if (!preg_match('/^\d{10}$/', $phone)) {
        return ["success" => false, "message" => "Phone number must be a valid 10-digit number.", "_code" => 400];
    }
    
    // Validate strong password
    $pwError = validateStrongPassword($password);
    if ($pwError) {
        return ["success" => false, "message" => $pwError, "_code" => 400];
    }
    
    // Check if username exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return ["success" => false, "message" => "Username is already taken.", "_code" => 409];
    }
    
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("INSERT INTO users (name, username, email, phone, password, role, job_role) VALUES (?, ?, ?, ?, ?, 'staff', ?)");
    $stmt->execute([$name, $username, $email, $phone, $hashedPassword, $job_role]);
    
    return ["success" => true, "message" => "Staff member added successfully."];
}

// Update a staff member
function updateStaff($data) {
    global $pdo;
    $id = intval($data['id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $phone = trim($data['phone'] ?? '');
    $job_role = trim($data['job_role'] ?? '');
    $password = trim($data['password'] ?? '');
    
    if (strtolower($job_role) === 'admin') {
        return ["success" => false, "message" => "Cannot assign Admin role.", "_code" => 403];
    }

    if (!$id || !$name || !$username || !$email || !$phone) {
        return ["success" => false, "message" => "Missing required fields.", "_code" => 400];
    }

    if (!preg_match('/^\d{10}$/', $phone)) {
        return ["success" => false, "message" => "Phone number must be a valid 10-digit number.", "_code" => 400];
    }
    
    // Check username uniqueness
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $id]);
    if ($stmt->fetch()) {
        return ["success" => false, "message" => "Username is already taken.", "_code" => 409];
    }
    
    if ($password) {
        // Enforce strong password
        $pwError = validateStrongPassword($password);
        if ($pwError) {
            return ["success" => false, "message" => $pwError, "_code" => 400];
        }
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, phone = ?, password = ?, job_role = ? WHERE id = ?");
        $stmt->execute([$name, $username, $email, $phone, $hashedPassword, $job_role, $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, username = ?, email = ?, phone = ?, job_role = ? WHERE id = ?");
        $stmt->execute([$name, $username, $email, $phone, $job_role, $id]);
    }
    
    return ["success" => true, "message" => "Staff member updated successfully."];
}

// Delete a staff member
function deleteStaff($data) {
    global $pdo;
    $id = intval($data['id'] ?? 0);
    
    if (!$id) return ["success" => false, "message" => "Invalid ID.", "_code" => 400];
    
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'staff'");
    $stmt->execute([$id]);
    
    return ["success" => true, "message" => "Staff member removed."];
}
