<?php
namespace App\Core;

use App\Models\User;

final class Auth
{
    private static ?array $cachedUser = null;

    public static function login(int $userId): void
    {
        $token = bin2hex(random_bytes(16));
        $_SESSION['user_id']   = $userId;
        $_SESSION['user_token'] = $token;
        self::$cachedUser = null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        self::$cachedUser = null;
    }

    public static function check(): bool
    {
        return self::currentUserId() !== null;
    }

    public static function currentUserId(): ?int
    {
        if (isset($_SESSION['user_id'])) {
            return (int)$_SESSION['user_id'];
        }
        return null;
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
}
