<?php
/**
 * Router for PHP built-in server
 * Handles routing requests to API endpoints outside the public directory
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route API requests to the api directory
if (preg_match('#^/api/(.+)$#', $uri, $matches)) {
    $apiPath = __DIR__ . '/../api/' . $matches[1];

    // Add .php extension if not present
    if (!pathinfo($apiPath, PATHINFO_EXTENSION)) {
        $apiPath .= '.php';
    }

    if (file_exists($apiPath)) {
        require $apiPath;
        return true;
    } else {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Endpoint not found']);
        return true;
    }
}

// Serve static files or default to serving the requested file
return false;
?>
