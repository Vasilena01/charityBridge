<?php
namespace App\Core;

final class Url
{
    public static string $prefix = '';

    public static function init(string $scriptName, string $requestUri = '', string $marker = ''): void
    {
        $prefix = self::prefixFromScript($scriptName);

        if ($prefix === null && $marker !== '' && $requestUri !== '') {
            $prefix = self::prefixFromMarker($requestUri, $marker);
        }

        self::$prefix = $prefix ?? '';
    }

    public static function to(string $path = ''): string
    {
        $path = ltrim($path, '/');
        $base = self::$prefix === '' ? '' : self::$prefix;
        return ($base === '' ? '' : $base) . '/' . $path;
    }

    public static function asset(string $path): string
    {
        return self::to('assets/' . ltrim($path, '/'));
    }

    public static function stripPrefix(string $requestPath): string
    {
        $path = $requestPath;
        if (self::$prefix !== '' && str_starts_with($path, self::$prefix)) {
            $path = substr($path, strlen(self::$prefix));
        }
        if (str_starts_with($path, '/public/') || $path === '/public') {
            $path = substr($path, strlen('/public'));
        }
        return $path === '' ? '/' : $path;
    }

    private static function prefixFromScript(string $scriptName): ?string
    {
        if ($scriptName === '' || !str_ends_with($scriptName, '/index.php')) {
            return null;
        }
        $prefix = substr($scriptName, 0, -strlen('/index.php'));
        if (str_ends_with($prefix, '/public')) {
            $prefix = substr($prefix, 0, -strlen('/public'));
        }
        return $prefix;
    }

    private static function prefixFromMarker(string $requestUri, string $marker): ?string
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        $needle = '/' . $marker;
        $idx = strpos($path, $needle);
        if ($idx === false) {
            return null;
        }
        return substr($path, 0, $idx + strlen($marker) + 1);
    }
}
