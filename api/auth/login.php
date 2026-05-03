<?php
require_once '../../backend/includes/config.php';
require_once '../../backend/includes/auth.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$response = ['success' => false];

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Fallback to POST if JSON not provided
    if (!$input) {
        $input = $_POST;
    }

    // CSRF token validation
    if (!isset($input['csrf_token']) || $input['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid request. Please try again.');
    }

    $email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $input['password'] ?? '';

    // Validation
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Valid email is required.');
    }
    if (empty($password)) {
        throw new Exception('Password is required.');
    }

    // Authenticate user
    $stmt = $pdo->prepare("SELECT id, email, password_hash, role, first_name, last_name FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    // Always hash even if user not found (prevent timing attacks)
    $hash = $user ? $user['password_hash'] : password_hash('dummy', PASSWORD_DEFAULT);

    if ($user && password_verify($password, $hash)) {
        // Successful login - regenerate session ID (prevent session fixation)
        session_regenerate_id(true);

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_first_name'] = $user['first_name'];
        $_SESSION['user_last_name'] = $user['last_name'];

        $response['success'] = true;
        $response['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role']
        ];
    } else {
        throw new Exception('Invalid email or password.');
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
