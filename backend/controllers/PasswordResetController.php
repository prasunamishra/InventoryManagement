<?php
require_once __DIR__ . '/../config/db.php';          // database connection
require_once __DIR__ . '/../config/helpers.php';    // helper functions
require_once __DIR__ . '/../config/mail.php';       // mail config (SMTP details)
require_once __DIR__ . '/../config/SimpleMailer.php'; // mail send garne class

// SEND RESET LINK 
// user ko email ma password reset link pathaune
function sendResetLink(string $email, string $role = 'admin'): array
{
    global $pdo;

    // email + role database ma xa ki check
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = ?");
    $stmt->execute([$email, $role]);
    $user = $stmt->fetch();

    if (!$user) {
        return ["success" => false, "message" => "Email not found."];
    }

    // secure random token generate garne
    $token = bin2hex(random_bytes(32));

    // token hash garne (security ko lagi)
    $hashedToken = hash('sha256', $token);

    // token database ma save (30 min samma valid)
    $stmt = $pdo->prepare(
        "INSERT INTO password_resets (email, token, expires_at, role)
         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), ?)"
    );
    $stmt->execute([$email, $hashedToken, $role]);

    // reset link banaune
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'];

    $resetLink = $protocol . "://" . $host .
        "/groceryflowupdate/frontend/html/reset_password.html?token=" . $token;

    // email body (HTML format)
    $body = "
        <h2>Password Reset</h2>
        <p>Reset garna tala click garnu:</p>
        <a href='{$resetLink}'>Reset Password</a>
        <p>Yo link 30 min samma matra valid hunxa.</p>
    ";

    // email send garne
    try {
        $mailer = new SimpleMailer(SMTP_HOST, SMTP_PORT, SMTP_USERNAME, SMTP_PASSWORD);

        $mailer->send(
            SMTP_FROM_EMAIL,
            SMTP_FROM_NAME,
            $email,
            'Password Reset Request',
            $body
        );

    } catch (RuntimeException $e) {
        return ["success" => false, "message" => "Email send failed."];
    }

    return ["success" => true, "message" => "Reset link email ma pathaiyo."];
}

//  VERIFY TOKEN 
// token valid xa ki expire vayo check
function verifyToken(string $token): array
{
    global $pdo;

    $hashedToken = hash('sha256', $token); // hash garera compare

    $stmt = $pdo->prepare(
        "SELECT email, role FROM password_resets 
         WHERE token = ? AND expires_at > NOW()"
    );
    $stmt->execute([$hashedToken]);
    $record = $stmt->fetch();

    // invalid ya expired
    if (!$record) {
        return ["success" => false, "message" => "Invalid or expired token."];
    }

    return [
        "success" => true,
        "message" => "Token valid.",
        "email" => $record['email'],
        "role" => $record['role']
    ];
}

//  RESET PASSWORD 
// valid token use garera password change garne
function resetPassword(string $token, string $newPassword): array
{
    global $pdo;

    // pahila token verify garne
    $verification = verifyToken($token);
    if (!$verification['success']) {
        return $verification;
    }

    $email = $verification['email'];
    $role = $verification['role'];

    $hashedToken = hash('sha256', $token);

    // strong password check garne
    $pwError = validateStrongPassword($newPassword);
    if ($pwError) {
        return ["success" => false, "message" => $pwError];
    }

    // password hash garne (bcrypt)
    $hashed = password_hash($newPassword, PASSWORD_BCRYPT);

    // database ma update garne
    $stmt = $pdo->prepare(
        "UPDATE users SET password = ? WHERE email = ? AND role = ?"
    );
    $stmt->execute([$hashed, $email, $role]);

    // token delete garne (reuse nahos)
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
    $stmt->execute([$hashedToken]);

    return ["success" => true, "message" => "Password updated."];
}