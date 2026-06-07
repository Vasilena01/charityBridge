<?php
namespace App\Models;

use App\Core\Db;

final class Contribution
{
    public static function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT * FROM contributions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listForUser(int $userId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT c.*, ca.title AS campaign_title
             FROM contributions c
             JOIN campaigns ca ON ca.id = c.campaign_id
             WHERE c.contributor_id = :uid
             ORDER BY c.created_at DESC
             LIMIT 200'
        );
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function listForCampaign(int $campaignId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT c.*, u.first_name, u.last_name, u.role AS contributor_role
             FROM contributions c
             JOIN users u ON u.id = c.contributor_id
             WHERE c.campaign_id = :cid
             ORDER BY c.created_at DESC'
        );
        $stmt->execute(['cid' => $campaignId]);
        return $stmt->fetchAll();
    }

    public static function summaryForCampaign(int $campaignId): array
    {
        $stmt = Db::pdo()->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN type='monetary' AND status='completed' THEN amount END), 0) AS monetary_total,
                COUNT(CASE WHEN type='monetary' AND status='completed' THEN 1 END) AS monetary_count,
                COALESCE(SUM(CASE WHEN type='hours' AND status IN ('pending','fulfilled') THEN hours_count END), 0) AS hours_total,
                COUNT(CASE WHEN type='hours' AND status IN ('pending','fulfilled') THEN 1 END) AS hours_count,
                COUNT(CASE WHEN type='goods' AND status IN ('pending','fulfilled') THEN 1 END) AS goods_count,
                COALESCE(SUM(CASE WHEN type='goods' AND status IN ('pending','fulfilled') THEN goods_estimated_value END), 0) AS goods_value
             FROM contributions
             WHERE campaign_id = :cid"
        );
        $stmt->execute(['cid' => $campaignId]);
        $row = $stmt->fetch() ?: [];
        return [
            'monetary_total' => (float)($row['monetary_total'] ?? 0),
            'monetary_count' => (int)($row['monetary_count'] ?? 0),
            'hours_total'    => (float)($row['hours_total'] ?? 0),
            'hours_count'    => (int)($row['hours_count'] ?? 0),
            'goods_count'    => (int)($row['goods_count'] ?? 0),
            'goods_value'    => (float)($row['goods_value'] ?? 0),
        ];
    }

    public static function createMonetary(int $campaignId, int $userId, float $amount, ?string $note): int
    {
        $stmt = Db::pdo()->prepare(
            "INSERT INTO contributions
                (campaign_id, contributor_id, type, amount, note, status)
             VALUES
                (:cid, :uid, 'monetary', :amount, :note, 'completed')"
        );
        $stmt->execute(['cid' => $campaignId, 'uid' => $userId, 'amount' => $amount, 'note' => $note]);
        return (int)Db::pdo()->lastInsertId();
    }

    public static function createHours(int $campaignId, int $userId, float $hours, ?string $note): int
    {
        $stmt = Db::pdo()->prepare(
            "INSERT INTO contributions
                (campaign_id, contributor_id, type, hours_count, note, status)
             VALUES
                (:cid, :uid, 'hours', :hours, :note, 'pending')"
        );
        $stmt->execute(['cid' => $campaignId, 'uid' => $userId, 'hours' => $hours, 'note' => $note]);
        return (int)Db::pdo()->lastInsertId();
    }

    public static function createGoods(int $campaignId, int $userId, string $description, ?float $estimatedValue, ?string $note): int
    {
        $stmt = Db::pdo()->prepare(
            "INSERT INTO contributions
                (campaign_id, contributor_id, type, goods_description, goods_estimated_value, note, status)
             VALUES
                (:cid, :uid, 'goods', :desc, :val, :note, 'pending')"
        );
        $stmt->execute(['cid' => $campaignId, 'uid' => $userId, 'desc' => $description, 'val' => $estimatedValue, 'note' => $note]);
        return (int)Db::pdo()->lastInsertId();
    }

    public static function cancel(int $id): void
    {
        Db::pdo()->prepare(
            "UPDATE contributions
             SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
             WHERE id = :id"
        )->execute(['id' => $id]);
    }
}
