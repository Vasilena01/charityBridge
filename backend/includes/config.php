<?php
// Database configuration
define('DB_TYPE', 'sqlite');  // Changed from MySQL to SQLite
define('DB_PATH', __DIR__ . '/../database/charity_bridge.db');
define('DB_CHARSET', 'utf8mb4');

// Application constants
define('SITE_URL', 'http://localhost/charity-bridge');
define('SITE_NAME', 'CharityBridge');
define('API_URL', SITE_URL . '/api');

// Password requirements
define('PASSWORD_MIN_LENGTH', 8);

// Session configuration
$sessionPath = __DIR__ . '/../sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');  // Changed from Strict to Lax for redirects
ini_set('session.cookie_path', '/');
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 86400);  // 24 hours
// ini_set('session.cookie_secure', 1);  // Uncomment for HTTPS

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once __DIR__ . '/db.php';
?>
