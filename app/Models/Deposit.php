<?php
namespace App\Models;

use App\Core\Db;

final class Deposit
{
    public static function listForUser(int $userId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT id, amount, balance_before, balance_after,
                    payment_method, card_last4, card_holder, status, created_at
             FROM deposits
             WHERE user_id = :uid
             ORDER BY created_at DESC
             LIMIT 50'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            "INSERT INTO deposits
                (user_id, amount, balance_before, balance_after,
                 payment_method, card_last4, card_holder, status)
             VALUES
                (:uid, :amount, :before, :after,
                 'mock_card', :last4, :holder, 'completed')"
        );
        $stmt->execute([
            'uid'    => $data['user_id'],
            'amount' => number_format((float)$data['amount'], 2, '.', ''),
            'before' => number_format((float)$data['balance_before'], 2, '.', ''),
            'after'  => number_format((float)$data['balance_after'], 2, '.', ''),
            'last4'  => $data['card_last4'],
            'holder' => $data['card_holder'],
        ]);
        return (int)Db::pdo()->lastInsertId();
    }
}
