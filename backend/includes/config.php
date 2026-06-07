<?php
require_once __DIR__ . '/env.php';
Env::load(__DIR__ . '/../../.env');

define('DB_TYPE', env('DB_DRIVER', 'sqlite'));
define('DB_PATH', __DIR__ . '/../../' . env('DB_SQLITE_PATH', 'backend/database/charity_bridge.db'));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', ''));
define('DB_USER', env('DB_USER', ''));
define('DB_PASS', env('DB_PASSWORD', ''));

define('PASSWORD_MIN_LENGTH', Env::int('PASSWORD_MIN_LENGTH', 8));

$sessionPath = __DIR__ . '/../sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.cookie_path', '/');
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_lifetime', 86400);

if (Env::bool('APP_DEBUG', true)) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';
