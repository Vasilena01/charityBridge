<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/includes/auth.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:8000');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');  // IMPORTANT: Allow credentials

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Debug: log session info
error_log("Session check - Session ID: " . session_id());
error_log("Session check - Session data: " . print_r($_SESSION, true));
error_log("Session check - is_logged_in: " . (is_logged_in() ? 'true' : 'false'));

if (is_logged_in()) {
    $user = get_current_user();

    // Check if user data is valid
    if ($user && is_array($user)) {
        error_log("Session check - User found: " . $user['email']);
        echo json_encode([
            'authenticated' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role'],
                'bio' => $user['bio'] ?? null
            ]
        ]);
    } else {
        // User session exists but data fetch failed
        error_log("Session check - User data fetch failed");
        echo json_encode(['authenticated' => false]);
    }
} else {
    error_log("Session check - Not logged in");
    echo json_encode(['authenticated' => false]);
}
?>
