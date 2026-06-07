<?php

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

require __DIR__ . '/../app/bootstrap.php';

$request = new Request();

try {
    $router = new Router(require __DIR__ . '/../routes.php');
    $router->dispatch($request);
} catch (\Throwable $e) {
    if (env('APP_DEBUG', true)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Unhandled error:\n\n" . $e::class . ': ' . $e->getMessage() . "\n\n" . $e->getTraceAsString();
    } else {
        Response::html('<h1>Server error</h1><p>Something went wrong. Please try again.</p>', 500);
    }
}
