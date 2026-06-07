<?php
namespace App\Core;

final class Request
{
    public string $method;
    public string $path;
    public array $query;
    public array $post;
    public array $cookies;
    public array $params = [];

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $rawPath       = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $this->path    = Url::stripPrefix($rawPath);
        $this->query   = $_GET;
        $this->post    = $_POST;
        $this->cookies = $_COOKIE;

        if ($this->method !== 'GET' && empty($this->post)) {
            $body = file_get_contents('php://input');
            if ($body !== '' && $body !== false) {
                $decoded = json_decode($body, true);
                if (is_array($decoded)) {
                    $this->post = $decoded;
                }
            }
        }
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }
}
