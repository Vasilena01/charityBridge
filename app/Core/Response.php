<?php
namespace App\Core;

final class Response
{
    public static function html(string $body, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $body;
    }

    public static function redirect(string $url, int $status = 302): void
    {
        http_response_code($status);
        header('Location: ' . $url);
        exit;
    }

    public static function notFound(string $message = 'Not found'): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo View::render('errors/404', ['message' => $message]);
        exit;
    }

    public static function forbidden(string $message = 'Forbidden'): void
    {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo View::render('errors/403', ['message' => $message]);
        exit;
    }
}
