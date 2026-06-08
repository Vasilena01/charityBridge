<?php

use App\Core\Env;
use App\Core\Url;
use App\Core\View;

$root = dirname(__DIR__);

spl_autoload_register(function (string $class) use ($root): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $relative = substr($class, 4);
    $file = $root . '/app/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once __DIR__ . '/Core/helpers.php';

if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}

Env::load($root . '/.env');

if (Env::bool('APP_DEBUG', true)) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

Url::init(
    $_SERVER['SCRIPT_NAME'] ?? '/index.php',
    $_SERVER['REQUEST_URI'] ?? '/',
    Env::get('APP_URL_MARKER', '')
);
View::setViewsDir($root . '/app/Views');
