<?php
/**
 * Check if user is logged in
 */
if (!function_exists('is_logged_in')) {
    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }
}

/**
 * Require login - redirect to login page if not authenticated
 */
if (!function_exists('require_login')) {
    function require_login() {
        if (!is_logged_in()) {
            header('Location: login.php');
            exit;
        }
    }
}

/**
 * Get current user data from session
 */
if (!function_exists('get_current_user')) {
    function get_current_user() {
        global $pdo;

        if (!is_logged_in()) {
            return null;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, email, first_name, last_name, role, bio FROM users WHERE id = :id");
            $stmt->execute(['id' => $_SESSION['user_id']]);
            $result = $stmt->fetch();

            return $result ? $result : null;
        } catch (Exception $e) {
            error_log("get_current_user error: " . $e->getMessage());
            return null;
        }
    }
}

/**
 * Check if current user has a specific role
 */
if (!function_exists('has_role')) {
    function has_role($role) {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }
}

/**
 * Redirect based on user role
 */
if (!function_exists('redirect_by_role')) {
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
}
?>
