<?php
namespace App\Models;

use App\Core\Db;
use PDO;

final class User
{
    public static function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, email, role, first_name, last_name, bio, virtual_balance
             FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, email, password_hash, role, first_name, last_name, bio, virtual_balance
             FROM users WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function emailExists(string $email): bool
    {
        $stmt = Db::pdo()->prepare('SELECT 1 FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        return (bool)$stmt->fetchColumn();
    }

    public static function create(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO users (email, password_hash, first_name, last_name, role, virtual_balance)
             VALUES (:email, :password_hash, :first_name, :last_name, :role, 0.00)'
        );
        $stmt->execute([
            'email'         => $data['email'],
            'password_hash' => $data['password_hash'],
            'first_name'    => $data['first_name'],
            'last_name'     => $data['last_name'],
            'role'          => $data['role'],
        ]);
        return (int)Db::pdo()->lastInsertId();
    }

    public static function getBalance(int $id): float
    {
        $stmt = Db::pdo()->prepare('SELECT virtual_balance FROM users WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return (float)$stmt->fetchColumn();
    }

    public static function setBalance(int $id, float $balance): void
    {
        $stmt = Db::pdo()->prepare('UPDATE users SET virtual_balance = :b WHERE id = :id');
        $stmt->execute(['b' => $balance, 'id' => $id]);
    }
}
