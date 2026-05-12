<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/../config/SimpleMailer.php';

// ─────────────────────────────────────────────────────────────
// 1. Send a password-reset link to the user's email
// ─────────────────────────────────────────────────────────────
function sendResetLink(string $email, string $role = 'admin'): array {
    global $pdo;

    // Check if the email exists for the given role
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    if (!$user) {
        return ["success" => false, "message" => "No account found with that email address for the selected role."];
    }

    // Generate a secure random token
    $token       = bin2hex(random_bytes(32));
    $hashedToken = hash('sha256', $token);

    // Store hashed token in DB, expires in 30 minutes
    $stmt = $pdo->prepare("INSERT INTO password_resets (email, token, expires_at, role) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), ?)");
    $stmt->execute([$email, $hashedToken, $role]);

    // Build the reset link
    $protocol  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host      = $_SERVER['HTTP_HOST'];
    $resetLink = $protocol . "://" . $host . "/groceryflowupdate/frontend/html/reset_password.html?token=" . $token;

    // Build HTML email body
    $body = "
        <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
            <h2 style='color: #0d9488;'>Password Reset</h2>
            <p>We received a request to reset your password for GroceryFlow. Click the button below to set a new password:</p>
            <p>
                <a href='{$resetLink}'
                   style='display:inline-block; padding:10px 20px; background:#0d9488; color:#fff; text-decoration:none; border-radius:5px; font-weight:bold;'>
                   Reset Password
                </a>
            </p>
            <p style='margin-top:20px; font-size:14px; color:#666;'>
                If the button doesn't work, copy and paste this link:<br>{$resetLink}
            </p>
            <p style='font-size:14px; color:#666;'>
                If you didn't request this, you can safely ignore this email. The link expires in 30 minutes.
            </p>
        </div>
    ";

    // Send via SimpleMailer
    try {
        $mailer = new SimpleMailer(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD);
        $mailer->send(SMTP_FROM_EMAIL, SMTP_FROM_NAME, $email, 'Password Reset Request', $body);
    } catch (RuntimeException $e) {
        return ["success" => false, "message" => "Could not send email: " . $e->getMessage()];
    }

    return ["success" => true, "message" => "An email has been sent to your address with a reset link. Please check your inbox."];
}

// ─────────────────────────────────────────────────────────────
// 2. Verify a reset token is valid and not expired
// ─────────────────────────────────────────────────────────────
function verifyToken(string $token): array {
    global $pdo;

    $hashedToken = hash('sha256', $token);

    $stmt = $pdo->prepare("SELECT email, role FROM password_resets WHERE token = ? AND expires_at > NOW()");
    $stmt->execute([$hashedToken]);
    $record = $stmt->fetch();

    if (!$record) {
        return ["success" => false, "message" => "Invalid or expired token."];
    }

    return ["success" => true, "message" => "Token is valid.", "email" => $record['email'], "role" => $record['role']];
}

// ─────────────────────────────────────────────────────────────
// 3. Reset the password using a valid token
// ─────────────────────────────────────────────────────────────
function resetPassword(string $token, string $newPassword): array {
    global $pdo;

    // Verify the token first
    $verification = verifyToken($token);
    if (!$verification['success']) {
        return $verification;
    }

    $email       = $verification['email'];
    $role        = $verification['role'];
    $hashedToken = hash('sha256', $token);

    // Update the user's password (bcrypt hash)
    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt   = $pdo->prepare("UPDATE users SET password = ? WHERE email = ? AND role = ?");
    $stmt->execute([$hashed, $email, $role]);

    // Delete the used token so it can't be reused
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
    $stmt->execute([$hashedToken]);

    return ["success" => true, "message" => "Password updated successfully."];
}
