<?php
final class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            throw new RuntimeException("Missing .env file at $path. Copy .env.example to .env and fill it in.");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $eq = strpos($line, '=');
            if ($eq === false) {
                continue;
            }
            $key = trim(substr($line, 0, $eq));
            $value = trim(substr($line, $eq + 1));
            if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                $quote = $value[0];
                if (strlen($value) >= 2 && substr($value, -1) === $quote) {
                    $value = substr($value, 1, -1);
                }
            }
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }

        $secret = $_ENV['AUTH_SECRET'] ?? '';
        if ($secret === '' || str_starts_with($secret, 'replace-me')) {
            throw new RuntimeException('AUTH_SECRET is unset or still the placeholder. Generate one with: php -r "echo bin2hex(random_bytes(32));"');
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }
        $v = getenv($key);
        return $v === false ? $default : $v;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower((string)$v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int)$v;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $v = self::get($key);
        return $v === null ? $default : (float)$v;
    }
}

function env(string $key, mixed $default = null): mixed
{
    return Env::get($key, $default);
}
