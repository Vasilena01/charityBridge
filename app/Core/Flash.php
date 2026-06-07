<?php
namespace App\Core;

final class Flash
{
    private const COOKIE_NAME = 'flash';

    public static function set(string $type, string $message): void
    {
        $payload = json_encode(['type' => $type, 'message' => $message]);
        $path = Url::$prefix === '' ? '/' : Url::$prefix . '/';
        setcookie(self::COOKIE_NAME, $payload, [
            'expires'  => time() + 60,
            'path'     => $path,
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => Env::get('APP_ENV', 'local') !== 'local',
        ]);
        $_COOKIE[self::COOKIE_NAME] = $payload;
    }

    public static function consume(): ?array
    {
        $raw = $_COOKIE[self::COOKIE_NAME] ?? null;
        if ($raw === null) {
            return null;
        }
        $path = Url::$prefix === '' ? '/' : Url::$prefix . '/';
        setcookie(self::COOKIE_NAME, '', [
            'expires'  => time() - 3600,
            'path'     => $path,
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => Env::get('APP_ENV', 'local') !== 'local',
        ]);
        unset($_COOKIE[self::COOKIE_NAME]);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
