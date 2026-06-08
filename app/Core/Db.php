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

        $host    = Env::get('DB_HOST');
        $port    = Env::int('DB_PORT', 3306);
        $name    = Env::get('DB_NAME');
        $user    = Env::get('DB_USER');
        $pass    = Env::get('DB_PASSWORD');
        $charset = Env::get('DB_CHARSET', 'utf8mb4');

        try {
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
        } catch (PDOException $e) {
            throw new RuntimeException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }
}
