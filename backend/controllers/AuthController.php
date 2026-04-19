<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

function handleLogin($data) {
    global $pdo;
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');

    if (!$username || !$password) {
        return ['success' => false, 'message' => 'Username and password are required.', '_code' => 400];
    }

    $stmt = $pdo->prepare("
      SELECT u.id, u.username, u.name, u.password, u.role as account_role, u.product_permission, s.role as job_role 
      FROM users u 
      LEFT JOIN staff s ON u.username = s.username 
      WHERE u.username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username or password.', '_code' => 401];
    }

    if (password_verify($password, $user['password'])) {
        $authed = true;
    } elseif ($user['password'] === $password) {
        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $upd = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->execute([$newHash, $user['id']]);
        $authed = true;
    } else {
        return ['success' => false, 'message' => 'Invalid username or password.', '_code' => 401];
    }

    $remember = !empty($data['remember']);

    if ($remember) {
        $lifetime = 60 * 60 * 24 * 30; // 30 days
        session_set_cookie_params($lifetime);
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    session_regenerate_id(true);

    if ($remember) {
        setcookie(session_name(), session_id(), time() + $lifetime, "/");
    }

    $_SESSION['user_id']  = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role']     = $user['account_role'];
    $_SESSION['job_role'] = $user['job_role'];
    $_SESSION['product_permission'] = $user['product_permission'];

    return [
        "success"  => true,
        "name"     => $user['name'],
        "username" => $user['username'],
        "role"     => $user['account_role'],
        "job_role" => $user['job_role'],
        "product_permission" => $user['product_permission']
    ];
}

function handleLogout() {
    session_start();
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
    return ["success" => true, "message" => "Logged out securely."];
}
