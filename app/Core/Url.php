<?php
namespace App\Core;

final class Url
{
    public static string $prefix = '';

    public static function init(string $requestUri, string $marker): void
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        $needle = '/' . $marker;
        $idx = strpos($path, $needle);
        self::$prefix = $idx === false ? '' : substr($path, 0, $idx + strlen($marker) + 1);
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
        if (self::$prefix === '') {
            return $requestPath;
        }
        if (str_starts_with($requestPath, self::$prefix)) {
            $stripped = substr($requestPath, strlen(self::$prefix));
            return $stripped === '' ? '/' : $stripped;
        }
        return $requestPath;
    }
}
