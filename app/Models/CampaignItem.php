<?php
namespace App\Models;

use App\Core\Db;

final class CampaignItem
{
    public static function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM campaign_items WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findWithOrganizer(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT i.*, c.organizer_id
             FROM campaign_items i
             JOIN campaigns c ON c.id = i.campaign_id
             WHERE i.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listForCampaign(int $campaignId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT * FROM campaign_items
             WHERE campaign_id = :cid AND status != 'removed'
             ORDER BY created_at ASC"
        );
        $stmt->execute(['cid' => $campaignId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            "INSERT INTO campaign_items
                (campaign_id, producer_id, name, description, item_type, production_cost, donation_amount, quantity_available, status)
             VALUES
                (:campaign_id, :producer_id, :name, :description, :item_type, :production_cost, :donation_amount, :quantity_available, 'active')"
        );
        $stmt->execute([
            'campaign_id'        => $data['campaign_id'],
            'producer_id'        => $data['producer_id'] ?? null,
            'name'               => $data['name'],
            'description'        => $data['description'] ?? null,
            'item_type'          => $data['item_type'],
            'production_cost'    => $data['production_cost'],
            'donation_amount'    => $data['donation_amount'],
            'quantity_available' => $data['quantity_available'],
        ]);
        return (int)Db::pdo()->lastInsertId();
    }

    public static function update(int $id, array $fields): void
    {
        $allowed = ['name', 'description', 'item_type', 'production_cost', 'donation_amount', 'quantity_available', 'status'];
        $sets    = [];
        $params  = ['id' => $id];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $fields)) {
                $sets[]     = "$f = :$f";
                $params[$f] = $fields[$f];
            }
        }
        if (empty($sets)) {
            return;
        }
        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
        Db::pdo()->prepare('UPDATE campaign_items SET ' . implode(', ', $sets) . ' WHERE id = :id')
            ->execute($params);
    }

    public static function delete(int $id): void
    {
        Db::pdo()->prepare('DELETE FROM campaign_items WHERE id = :id')->execute(['id' => $id]);
    }

    public static function recordSale(int $id, int $newSold, string $newStatus): void
    {
        Db::pdo()->prepare(
            'UPDATE campaign_items
             SET quantity_sold = :sold, status = :status, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        )->execute(['sold' => $newSold, 'status' => $newStatus, 'id' => $id]);
    }
}
