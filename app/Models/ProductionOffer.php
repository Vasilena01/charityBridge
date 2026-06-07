<?php
namespace App\Models;

use App\Core\Db;

final class ProductionOffer
{
    public static function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM production_offers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findWithCampaign(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT o.*, c.organizer_id, c.status AS campaign_status
             FROM production_offers o
             JOIN campaigns c ON c.id = o.campaign_id
             WHERE o.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listForUser(int $userId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT o.*, c.title AS campaign_title
             FROM production_offers o
             JOIN campaigns c ON c.id = o.campaign_id
             WHERE o.producer_id = :uid
             ORDER BY o.created_at DESC'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function listForCampaign(int $campaignId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT o.*, u.first_name, u.last_name, u.email AS producer_email, u.role AS producer_role
             FROM production_offers o
             JOIN users u ON u.id = o.producer_id
             WHERE o.campaign_id = :cid
             ORDER BY
                 CASE o.status WHEN 'pending' THEN 0 ELSE 1 END,
                 o.created_at DESC"
        );
        $stmt->execute(['cid' => $campaignId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            "INSERT INTO production_offers
                (campaign_id, producer_id, name, description, item_type,
                 proposed_production_cost, proposed_donation_amount, quantity_offered, status)
             VALUES
                (:campaign_id, :producer_id, :name, :description, :item_type,
                 :cost, :donation, :qty, 'pending')"
        );
        $stmt->execute([
            'campaign_id' => $data['campaign_id'],
            'producer_id' => $data['producer_id'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'item_type'   => $data['item_type'],
            'cost'        => $data['proposed_production_cost'],
            'donation'    => $data['proposed_donation_amount'],
            'qty'         => $data['quantity_offered'],
        ]);
        return (int)Db::pdo()->lastInsertId();
    }

    public static function setRejected(int $id, ?string $note): void
    {
        Db::pdo()->prepare(
            "UPDATE production_offers
             SET status = 'rejected', organizer_note = :note,
                 decided_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        )->execute(['id' => $id, 'note' => $note]);
    }

    public static function setCancelled(int $id): void
    {
        Db::pdo()->prepare(
            "UPDATE production_offers
             SET status = 'cancelled', decided_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        )->execute(['id' => $id]);
    }

    public static function setAccepted(int $id, int $itemId): void
    {
        Db::pdo()->prepare(
            "UPDATE production_offers
             SET status = 'accepted', accepted_item_id = :item_id,
                 decided_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        )->execute(['item_id' => $itemId, 'id' => $id]);
    }
}
