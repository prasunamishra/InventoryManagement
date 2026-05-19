<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';

function handleLogin($data)
{
    global $pdo;

    // trim the inputs so extra spaces don't cause "wrong password" headaches
    $username   = trim($data['username']   ?? '');
    $password   = trim($data['password']   ?? '');
    $login_role = trim($data['login_role'] ?? 'admin'); // default to admin if not specified

    // basic validation - both fields are required
    if (empty($username) || empty($password)) {
        return ['success' => false, 'message' => 'Username and password are required.', '_code' => 400];
    }

    // look up the user in the DB, but ONLY if their role matches what they're trying to log in as
    // this prevents a staff member from logging in through the admin login form
    $sql = "
      SELECT id, username, name, password, role as account_role, product_permission, job_role 
      FROM users 
      WHERE username = ? AND role = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username, $login_role]);
    $user = $stmt->fetch();

    // user not found OR wrong role - return a vague error (don't reveal which one)
    if ($user == false) {
        return ['success' => false, 'message' => 'Invalid username or password.', '_code' => 401];
    }

    // check if the password matches using PHP's built-in bcrypt verification
    $is_password_correct = password_verify($password, $user['password']);

    if ($is_password_correct) {
        $authed = true;
    } else {
        // legacy path: some old accounts still have plain-text passwords
        // if the raw password matches, let them in AND upgrade to a proper hash
        if ($user['password'] === $password) {
            $newHash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newHash, $user['id']]);
            $authed = true;
        } else {
            return ['success' => false, 'message' => 'Invalid username or password.', '_code' => 401];
        }
    }

    // "remember me" checkbox - extends the session cookie to 30 days
    $remember = !empty($data['remember']);

    if ($remember) {
        $lifetime = 60 * 60 * 24 * 30; // 30 days in seconds
        session_set_cookie_params($lifetime);
    }

    // start the session (check first to avoid "already started" warnings)
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // regenerate the session ID to prevent session fixation attacks
    session_regenerate_id(true);

    // if "remember me" was checked, also manually set the cookie with the longer expiry
    if ($remember) {
        setcookie(session_name(), session_id(), time() + $lifetime, "/");
    }

    // store everything we'll need to know about this user throughout their session
    $_SESSION['user_id']            = $user['id'];
    $_SESSION['username']           = $user['username'];
    $_SESSION['name']               = $user['name'];
    $_SESSION['role']               = $user['account_role'];
    $_SESSION['job_role']           = $user['job_role'];
    $_SESSION['product_permission'] = $user['product_permission'];

    // return the user's info to the frontend so it can update the UI
    return [
        "success"            => true,
        "name"               => $user['name'],
        "username"           => $user['username'],
        "role"               => $user['account_role'],
        "job_role"           => $user['job_role'],
        "product_permission" => $user['product_permission']
    ];
}

function handleLogout()
{
    session_start();

    // wipe all session variables
    session_unset();

    // destroy the session on the server
    session_destroy();

    // also expire the session cookie in the browser by setting it to the past
    setcookie(session_name(), '', time() - 3600, '/');

    return ["success" => true, "message" => "Logged out securely."];
}
