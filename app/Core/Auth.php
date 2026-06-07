<?php
namespace App\Core;

use App\Models\RevokedToken;
use App\Models\User;

final class Auth
{
    private static ?array $cachedUser = null;
    private static ?int $cachedUserId = null;
    private static bool $resolved = false;

    public static function login(int $userId): void
    {
        $token = self::issue($userId);
        self::setCookie($token);
        self::$cachedUser   = null;
        self::$cachedUserId = $userId;
        self::$resolved     = true;
    }

    public static function logout(): void
    {
        $cookieName = self::cookieName();
        $raw = $_COOKIE[$cookieName] ?? null;
        if ($raw !== null) {
            $payload = self::verify($raw, skipRevocationCheck: true);
            if ($payload !== null) {
                RevokedToken::revoke($payload['jti'], (int)$payload['uid'], (int)$payload['exp']);
            }
        }
        self::clearCookie();
        self::$cachedUser   = null;
        self::$cachedUserId = null;
        self::$resolved     = true;
    }

    public static function check(): bool
    {
        return self::currentUserId() !== null;
    }

    public static function currentUserId(): ?int
    {
        if (self::$resolved) {
            return self::$cachedUserId;
        }
        self::$resolved = true;

        $cookieName = self::cookieName();
        $raw = $_COOKIE[$cookieName] ?? null;
        if ($raw === null) {
            return null;
        }

        $payload = self::verify($raw);
        if ($payload === null) {
            self::clearCookie();
            return null;
        }

        self::$cachedUserId = (int)$payload['uid'];
        return self::$cachedUserId;
    }

    public static function user(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }
        $id = self::currentUserId();
        if ($id === null) {
            return null;
        }
        self::$cachedUser = User::find($id);
        return self::$cachedUser;
    }

    public static function require(): array
    {
        $user = self::user();
        if ($user === null) {
            Response::redirect(Url::to('login'));
        }
        return $user;
    }

    public static function requireRole(string ...$roles): array
    {
        $user = self::require();
        if (!in_array($user['role'], $roles, true)) {
            Response::forbidden('Your role does not have access to this page.');
        }
        return $user;
    }

    public static function refresh(): void
    {
        self::$cachedUser = null;
    }

    public static function issue(int $userId): string
    {
        $now    = time();
        $ttl    = (int)Env::int('AUTH_TOKEN_TTL_SECONDS', 2592000);
        $payload = [
            'jti' => bin2hex(random_bytes(16)),
            'uid' => $userId,
            'iat' => $now,
            'exp' => $now + $ttl,
        ];
        $body = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $sig  = self::base64UrlEncode(self::hmac($body));
        return $body . '.' . $sig;
    }

    public static function verify(string $token, bool $skipRevocationCheck = false): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }
        [$body, $sig] = $parts;

        $expected = self::base64UrlEncode(self::hmac($body));
        if (!hash_equals($expected, $sig)) {
            return null;
        }

        $json = self::base64UrlDecode($body);
        if ($json === null) {
            return null;
        }
        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return null;
        }
        foreach (['jti', 'uid', 'iat', 'exp'] as $key) {
            if (!isset($payload[$key])) {
                return null;
            }
        }
        if ((int)$payload['exp'] < time()) {
            return null;
        }

        if (!$skipRevocationCheck && RevokedToken::isRevoked($payload['jti'])) {
            return null;
        }

        return $payload;
    }

    public static function setCookie(string $token): void
    {
        $cookieName = self::cookieName();
        $ttl        = (int)Env::int('AUTH_TOKEN_TTL_SECONDS', 2592000);
        $secure     = Env::get('APP_ENV', 'local') !== 'local';
        $path       = Url::$prefix === '' ? '/' : Url::$prefix . '/';

        setcookie($cookieName, $token, [
            'expires'  => time() + $ttl,
            'path'     => $path,
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $secure,
        ]);
        $_COOKIE[$cookieName] = $token;
    }

    public static function clearCookie(): void
    {
        $cookieName = self::cookieName();
        $secure     = Env::get('APP_ENV', 'local') !== 'local';
        $path       = Url::$prefix === '' ? '/' : Url::$prefix . '/';

        setcookie($cookieName, '', [
            'expires'  => time() - 3600,
            'path'     => $path,
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $secure,
        ]);
        unset($_COOKIE[$cookieName]);
    }

    private static function cookieName(): string
    {
        return (string)Env::get('AUTH_COOKIE_NAME', 'auth');
    }

    private static function hmac(string $data): string
    {
        $secret = (string)Env::get('AUTH_SECRET', '');
        return hash_hmac('sha256', $data, $secret, true);
    }

    private static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $b64): ?string
    {
        $padded = $b64;
        $padding = strlen($padded) % 4;
        if ($padding > 0) {
            $padded .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($padded, '-_', '+/'), true);
        return $decoded === false ? null : $decoded;
    }
}
