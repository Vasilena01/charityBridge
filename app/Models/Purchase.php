<?php
namespace App\Models;

use App\Core\Db;

final class Purchase
{
    public static function listForUser(int $userId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT p.*, i.name AS item_name, i.item_type, c.title AS campaign_title
             FROM purchases p
             JOIN campaign_items i ON i.id = p.item_id
             JOIN campaigns c ON c.id = p.campaign_id
             WHERE p.buyer_id = :uid
             ORDER BY p.created_at DESC
             LIMIT 100'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO purchases
                (item_id, buyer_id, campaign_id, quantity, unit_cost, unit_donation, total_paid, total_donation)
             VALUES
                (:item_id, :buyer_id, :campaign_id, :qty, :uc, :ud, :tp, :td)'
        );
        $stmt->execute([
            'item_id'     => $data['item_id'],
            'buyer_id'    => $data['buyer_id'],
            'campaign_id' => $data['campaign_id'],
            'qty'         => $data['quantity'],
            'uc'          => $data['unit_cost'],
            'ud'          => $data['unit_donation'],
            'tp'          => $data['total_paid'],
            'td'          => $data['total_donation'],
        ]);
        return (int)Db::pdo()->lastInsertId();
    }
}
