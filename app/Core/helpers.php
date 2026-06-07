<?php

use App\Core\Url;
use App\Core\View;
use App\Core\Auth;
use App\Core\Env;

if (!function_exists('e')) {
    function e(mixed $x): string
    {
        return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        return Url::to($path);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return Url::asset($path);
    }
}

if (!function_exists('partial')) {
    function partial(string $name, array $data = []): void
    {
        View::partial($name, $data);
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('old')) {
    function old(string $key, array $values = [], mixed $default = ''): mixed
    {
        return $values[$key] ?? $default;
    }
}
