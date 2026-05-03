<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'charity_bridge');
define('DB_USER', 'root');  // Change in production
define('DB_PASS', '');      // Change in production
define('DB_CHARSET', 'utf8mb4');

// Application constants
define('SITE_URL', 'http://localhost/charity-bridge');
define('SITE_NAME', 'CharityBridge');
define('API_URL', SITE_URL . '/api');

// Password requirements
define('PASSWORD_MIN_LENGTH', 8);

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
// ini_set('session.cookie_secure', 1);  // Uncomment for HTTPS

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
require_once __DIR__ . '/db.php';
?>
