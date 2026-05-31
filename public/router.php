<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (preg_match('#^/api/(.+?)/?$#', $uri, $matches)) {
    $apiBase = __DIR__ . '/../api/';
    $parts = explode('/', $matches[1]);

    for ($i = count($parts); $i > 0; $i--) {
        $prefix = implode('/', array_slice($parts, 0, $i));

        $phpFile = $apiBase . $prefix . '.php';
        if (is_file($phpFile)) {
            require $phpFile;
            return true;
        }

        $indexFile = $apiBase . $prefix . '/index.php';
        if (is_file($indexFile)) {
            require $indexFile;
            return true;
        }
    }

    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Endpoint not found',
        'uri' => $uri,
    ]);
    return true;
}

return false;
