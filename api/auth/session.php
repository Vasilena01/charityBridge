<?php
require_once '../../backend/includes/config.php';
require_once '../../backend/includes/auth.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (is_logged_in()) {
    $user = get_current_user();
    echo json_encode([
        'authenticated' => true,
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role'],
            'bio' => $user['bio']
        ]
    ]);
} else {
    echo json_encode(['authenticated' => false]);
}
?>
