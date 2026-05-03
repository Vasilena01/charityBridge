<?php
/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Require login - redirect to login page if not authenticated
 */
function require_login() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Get current user data from session
 */
function get_current_user() {
    global $pdo;

    if (!is_logged_in()) {
        return null;
    }

    $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role, bio FROM users WHERE id = :id");
    $stmt->execute(['id' => $_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Check if current user has a specific role
 */
function has_role($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

/**
 * Redirect based on user role
 */
function redirect_by_role() {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }

    // All roles go to dashboard for now
    // In future phases, can customize per role
    header('Location: dashboard.php');
    exit;
}
?>
