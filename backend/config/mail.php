<?php
/**
 * SMTP Mail Configuration
 * Used by SimpleMailer (no PHPMailer / no vendor needed).
 * Gmail requires port 465 with SSL for direct socket connections.
 */

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465);                            // SSL port (direct socket)
define('SMTP_USERNAME', 'abishekshrestha10@gmail.com');  // Your Gmail address
define('SMTP_PASSWORD', 'ahtc zoky dnch mrpg');          // Gmail App Password (16-char)
define('SMTP_FROM_EMAIL', 'abishekshrestha10@gmail.com');
define('SMTP_FROM_NAME', 'GroceryFlow System');
