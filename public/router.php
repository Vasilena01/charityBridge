<?php
/**
 * Router for PHP built-in server
 * Handles routing requests to API endpoints outside the public directory
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route API requests to the api directory
if (preg_match('#^/api/(.+?)/?$#', $uri, $matches)) {
    $apiPath = __DIR__ . '/../api/' . rtrim($matches[1], '/');

    // Check if it's a directory with index.php
    if (is_dir($apiPath) && file_exists($apiPath . '/index.php')) {
        require $apiPath . '/index.php';
        return true;
    }

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
        echo json_encode([
            'success' => false,
            'error' => 'Endpoint not found',
            'uri' => $uri,
            'tried' => $apiPath
        ]);
        return true;
    }
}

// Serve static files or default to serving the requested file
return false;
?>
