<?php
namespace App\Models;

use App\Core\Db;

final class RevokedToken
{
    private static bool $tableEnsured = false;

    public static function ensureTable(): void
    {
        if (self::$tableEnsured) {
            return;
        }
        self::$tableEnsured = true;

        Db::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS revoked_tokens (
                jti        CHAR(32)  NOT NULL PRIMARY KEY,
                user_id    INTEGER   NOT NULL,
                revoked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )'
        );
        $exists = Db::pdo()->query(
            "SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'revoked_tokens'
               AND index_name = 'idx_revoked_expires'"
        )->fetchColumn();
        if (!$exists) {
            Db::pdo()->exec('CREATE INDEX idx_revoked_expires ON revoked_tokens (expires_at)');
        }
    }

    public static function isRevoked(string $jti): bool
    {
        self::ensureTable();
        $stmt = Db::pdo()->prepare('SELECT 1 FROM revoked_tokens WHERE jti = :jti');
        $stmt->execute(['jti' => $jti]);
        return (bool)$stmt->fetchColumn();
    }

    public static function revoke(string $jti, int $userId, int $expiresAt): void
    {
        self::ensureTable();
        self::pruneExpired();

        $stmt = Db::pdo()->prepare(
            'INSERT INTO revoked_tokens (jti, user_id, expires_at)
             VALUES (:jti, :user_id, :expires_at)'
        );
        try {
            $stmt->execute([
                'jti'        => $jti,
                'user_id'    => $userId,
                'expires_at' => date('Y-m-d H:i:s', $expiresAt),
            ]);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE') || str_contains($e->getMessage(), 'Duplicate')) {
                return;
            }
            throw $e;
        }
    }

    public static function pruneExpired(): void
    {
        Db::pdo()->prepare('DELETE FROM revoked_tokens WHERE expires_at < :now')
            ->execute(['now' => date('Y-m-d H:i:s')]);
    }
}
