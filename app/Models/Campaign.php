<?php
namespace App\Models;

use App\Core\Db;

final class Campaign
{
    public static function find(int $id): ?array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT c.*, u.first_name, u.last_name, u.email AS organizer_email
             FROM campaigns c
             JOIN users u ON c.organizer_id = u.id
             WHERE c.id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findRaw(int $id): ?array
    {
        $stmt = Db::pdo()->prepare('SELECT id, organizer_id, status FROM campaigns WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listPublished(?string $type = null, ?string $search = null): array
    {
        $where  = ["c.status = 'published'"];
        $params = [];

        if ($type !== null && $type !== '') {
            $where[] = 'c.campaign_type = :type';
            $params['type'] = $type;
        }
        if ($search !== null && $search !== '') {
            $where[] = '(c.title LIKE :search OR c.description LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $sql = 'SELECT c.*, u.first_name, u.last_name
                FROM campaigns c
                JOIN users u ON c.organizer_id = u.id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY c.created_at DESC';

        $stmt = Db::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function listForOrganizer(int $organizerId): array
    {
        $stmt = Db::pdo()->prepare(
            'SELECT * FROM campaigns
             WHERE organizer_id = :organizer_id
             ORDER BY created_at DESC'
        );
        $stmt->execute(['organizer_id' => $organizerId]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $stmt = Db::pdo()->prepare(
            'INSERT INTO campaigns (organizer_id, title, description, campaign_type, goal_amount, deadline, status)
             VALUES (:organizer_id, :title, :description, :campaign_type, :goal_amount, :deadline, :status)'
        );
        $stmt->execute([
            'organizer_id'  => $data['organizer_id'],
            'title'         => $data['title'],
            'description'   => $data['description'],
            'campaign_type' => $data['campaign_type'],
            'goal_amount'   => $data['goal_amount'],
            'deadline'      => $data['deadline'],
            'status'        => $data['status'],
        ]);
        return (int)Db::pdo()->lastInsertId();
    }

    public static function update(int $id, array $fields): void
    {
        $allowed = ['title', 'description', 'campaign_type', 'goal_amount', 'deadline', 'status'];
        $sets    = [];
        $params  = ['id' => $id];
        foreach ($allowed as $f) {
            if (array_key_exists($f, $fields)) {
                $sets[]       = "$f = :$f";
                $params[$f]   = $fields[$f];
            }
        }
        if (empty($sets)) {
            return;
        }
        $sets[] = 'updated_at = CURRENT_TIMESTAMP';
        $sql    = 'UPDATE campaigns SET ' . implode(', ', $sets) . ' WHERE id = :id';
        Db::pdo()->prepare($sql)->execute($params);
    }

    public static function delete(int $id): void
    {
        Db::pdo()->prepare('DELETE FROM campaigns WHERE id = :id')->execute(['id' => $id]);
    }

    public static function addToCurrentAmount(int $id, float $amount): float
    {
        $pdo = Db::pdo();
        $pdo->prepare('UPDATE campaigns SET current_amount = current_amount + :a, updated_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute(['a' => $amount, 'id' => $id]);
        $stmt = $pdo->prepare('SELECT current_amount FROM campaigns WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return (float)$stmt->fetchColumn();
    }

    public static function canView(?array $campaign, ?int $userId): bool
    {
        if (!$campaign) {
            return false;
        }
        $uid = $userId ? (int)$userId : 0;
        if ($uid && (int)$campaign['organizer_id'] === $uid) {
            return true;
        }
        if ($campaign['status'] === 'draft') {
            return false;
        }
        return true;
    }

    public static function canActOn(?array $campaign, ?int $userId): bool
    {
        if (!$campaign) {
            return false;
        }
        if ($campaign['status'] !== 'published') {
            return false;
        }
        $uid = $userId ? (int)$userId : 0;
        if (!$uid) {
            return false;
        }
        if ((int)$campaign['organizer_id'] === $uid) {
            return false;
        }
        return true;
    }
}
