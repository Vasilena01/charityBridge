<?php
namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $driver = Env::get('DB_DRIVER', 'sqlite');

        try {
            if ($driver === 'sqlite') {
                $sqlitePath = Env::get('DB_SQLITE_PATH', 'backend/database/charity_bridge.db');
                $absPath = $sqlitePath[0] === '/' ? $sqlitePath : dirname(__DIR__, 2) . '/' . $sqlitePath;
                $dir = dirname($absPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0777, true);
                }
                self::$pdo = new PDO('sqlite:' . $absPath, null, null, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                self::$pdo->exec('PRAGMA foreign_keys = ON;');
            } else {
                $host    = Env::get('DB_HOST');
                $port    = Env::int('DB_PORT', 3306);
                $name    = Env::get('DB_NAME');
                $user    = Env::get('DB_USER');
                $pass    = Env::get('DB_PASSWORD');
                $charset = Env::get('DB_CHARSET', 'utf8mb4');

                self::$pdo = new PDO(
                    "mysql:host=$host;port=$port;dbname=$name;charset=$charset",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            }
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }
}
