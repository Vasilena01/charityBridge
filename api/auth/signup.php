<?php
require_once '../../backend/includes/config.php';

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

    // Sanitize and validate inputs
    $email = filter_var(trim($input['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $password = $input['password'] ?? '';
    $password_confirm = $input['password_confirm'] ?? '';
    $first_name = trim($input['first_name'] ?? '');
    $last_name = trim($input['last_name'] ?? '');
    $role = $input['role'] ?? '';

    // Validation
    $errors = [];

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required.';
    }
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        $errors[] = 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if (empty($first_name) || strlen($first_name) > 100) {
        $errors[] = 'First name is required (max 100 characters).';
    }
    if (empty($last_name) || strlen($last_name) > 100) {
        $errors[] = 'Last name is required (max 100 characters).';
    }
    if (!in_array($role, ['volunteer', 'organizer', 'company'])) {
        $errors[] = 'Please select a valid role.';
    }

    if (!empty($errors)) {
        throw new Exception(implode(' ', $errors));
    }

    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute(['email' => $email]);
    if ($stmt->fetch()) {
        throw new Exception('Email address is already registered.');
    }

    // Insert user
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare(
        "INSERT INTO users (email, password_hash, first_name, last_name, role)
         VALUES (:email, :password_hash, :first_name, :last_name, :role)"
    );
    $result = $stmt->execute([
        'email' => $email,
        'password_hash' => $password_hash,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => $role
    ]);

    if ($result) {
        $user_id = $pdo->lastInsertId();
        $response['success'] = true;
        $response['message'] = 'Registration successful! You can now log in.';
        $response['user_id'] = $user_id;
    } else {
        throw new Exception('Registration failed. Please try again.');
    }

} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>
