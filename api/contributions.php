<?php
require_once __DIR__ . '/../backend/includes/config.php';
require_once __DIR__ . '/../backend/includes/campaign_access.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5500');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $contribId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
    $campaignId = isset($_GET['campaign_id']) && is_numeric($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : null;
    $summary = isset($_GET['summary']) && $_GET['summary'] === 'true';
    $my = isset($_GET['my']) && $_GET['my'] === 'true';

    switch ($method) {
        case 'GET':
            if ($summary && $campaignId) {
                $response = summaryForCampaign($campaignId, $pdo);
            } elseif ($my) {
                $response = listMyContributions($pdo);
            } elseif ($campaignId) {
                $response = listContributionsForCampaign($campaignId, $pdo);
            } else {
                http_response_code(400);
                throw new Exception('Either my=true or campaign_id is required');
            }
            break;

        case 'POST':
            $response = createContribution($pdo);
            break;

        case 'PUT':
            if (!$contribId) {
                http_response_code(400);
                throw new Exception('Contribution id is required');
            }
            $response = cancelContribution($contribId, $pdo);
            break;

        default:
            http_response_code(405);
            throw new Exception('Method not allowed');
    }
} catch (Exception $e) {
    if (http_response_code() === 200) {
        http_response_code($e->getCode() ?: 500);
    }
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response);

function summaryForCampaign($campaignId, $pdo) {
    $stmt = $pdo->prepare("SELECT id, organizer_id, status, visibility FROM campaigns WHERE id = :id");
    $stmt->execute(['id' => $campaignId]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        http_response_code(404);
        throw new Exception('Campaign not found');
    }

    $viewerId = optionalAuthUserId();
    if (!can_view_campaign($campaign, $viewerId, $pdo)) {
        http_response_code(403);
        throw new Exception('This campaign is private');
    }

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type='monetary' AND status='completed' THEN amount END), 0) AS monetary_total,
            COUNT(CASE WHEN type='monetary' AND status='completed' THEN 1 END) AS monetary_count,
            COALESCE(SUM(CASE WHEN type='hours' AND status IN ('pending','fulfilled') THEN hours_count END), 0) AS hours_total,
            COUNT(CASE WHEN type='hours' AND status IN ('pending','fulfilled') THEN 1 END) AS hours_count,
            COUNT(CASE WHEN type='goods' AND status IN ('pending','fulfilled') THEN 1 END) AS goods_count,
            COALESCE(SUM(CASE WHEN type='goods' AND status IN ('pending','fulfilled') THEN goods_estimated_value END), 0) AS goods_value
        FROM contributions
        WHERE campaign_id = :cid
    ");
    $stmt->execute(['cid' => $campaignId]);
    $row = $stmt->fetch();

    return [
        'success' => true,
        'summary' => [
            'monetary_total' => (float)$row['monetary_total'],
            'monetary_count' => (int)$row['monetary_count'],
            'hours_total' => (float)$row['hours_total'],
            'hours_count' => (int)$row['hours_count'],
            'goods_count' => (int)$row['goods_count'],
            'goods_value' => (float)$row['goods_value'],
        ],
    ];
}

function listMyContributions($pdo) {
    $user = requireAuthUser($pdo);

    $stmt = $pdo->prepare("
        SELECT c.*, ca.title AS campaign_title
        FROM contributions c
        JOIN campaigns ca ON ca.id = c.campaign_id
        WHERE c.contributor_id = :uid
        ORDER BY c.created_at DESC
        LIMIT 200
    ");
    $stmt->execute(['uid' => $user['id']]);
    return ['success' => true, 'contributions' => $stmt->fetchAll()];
}

function listContributionsForCampaign($campaignId, $pdo) {
    $user = requireAuthUser($pdo);

    $stmt = $pdo->prepare("SELECT id, organizer_id FROM campaigns WHERE id = :id");
    $stmt->execute(['id' => $campaignId]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        http_response_code(404);
        throw new Exception('Campaign not found');
    }
    if ((int)$campaign['organizer_id'] !== (int)$user['id']) {
        http_response_code(403);
        throw new Exception('Only the campaign organizer can view contributions');
    }

    $stmt = $pdo->prepare("
        SELECT c.*, u.first_name, u.last_name, u.role AS contributor_role
        FROM contributions c
        JOIN users u ON u.id = c.contributor_id
        WHERE c.campaign_id = :cid
        ORDER BY c.created_at DESC
    ");
    $stmt->execute(['cid' => $campaignId]);
    return ['success' => true, 'contributions' => $stmt->fetchAll()];
}

function createContribution($pdo) {
    $user = requireAuthUser($pdo);
    if (!in_array($user['role'], ['volunteer', 'company'], true)) {
        http_response_code(403);
        throw new Exception('Only volunteers and companies can contribute');
    }

    $input = readJsonInput();
    $campaignId = isset($input['campaign_id']) ? (int)$input['campaign_id'] : 0;
    $type = $input['type'] ?? '';
    $note = isset($input['note']) ? trim((string)$input['note']) : null;
    if ($note === '') $note = null;

    if ($campaignId <= 0) {
        http_response_code(400);
        throw new Exception('campaign_id is required');
    }
    if (!in_array($type, ['monetary', 'hours', 'goods'], true)) {
        http_response_code(400);
        throw new Exception('type must be monetary, hours, or goods');
    }

    $stmt = $pdo->prepare("SELECT id, organizer_id, status, visibility FROM campaigns WHERE id = :id");
    $stmt->execute(['id' => $campaignId]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        http_response_code(404);
        throw new Exception('Campaign not found');
    }
    if (!can_act_on_campaign($campaign, (int)$user['id'], $pdo)) {
        if ((int)$campaign['organizer_id'] === (int)$user['id']) {
            http_response_code(403);
            throw new Exception('You cannot contribute to your own campaign');
        }
        if ($campaign['status'] !== 'published') {
            http_response_code(409);
            throw new Exception('Campaign is not accepting contributions');
        }
        http_response_code(403);
        throw new Exception('You need an accepted invitation to contribute to this private campaign');
    }

    if ($type === 'monetary') {
        return createMonetary($pdo, $user, $campaignId, $input, $note);
    } elseif ($type === 'hours') {
        return createHours($pdo, $user, $campaignId, $input, $note);
    } else {
        return createGoods($pdo, $user, $campaignId, $input, $note);
    }
}

function createMonetary($pdo, $user, $campaignId, $input, $note) {
    $amount = $input['amount'] ?? null;
    if (!is_numeric($amount) || (float)$amount <= 0) {
        http_response_code(400);
        throw new Exception('amount must be a positive number');
    }
    $amount = round((float)$amount, 2);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT virtual_balance FROM users WHERE id = :id");
        $stmt->execute(['id' => $user['id']]);
        $balance = (float)$stmt->fetchColumn();

        if ($balance < $amount) {
            http_response_code(402);
            throw new Exception(sprintf('Insufficient virtual balance: need %.2f, have %.2f', $amount, $balance));
        }

        $newBalance = $balance - $amount;
        $pdo->prepare("UPDATE users SET virtual_balance = :b WHERE id = :id")
            ->execute(['b' => $newBalance, 'id' => $user['id']]);

        $pdo->prepare("UPDATE campaigns SET current_amount = current_amount + :a, updated_at = CURRENT_TIMESTAMP WHERE id = :id")
            ->execute(['a' => $amount, 'id' => $campaignId]);

        $stmt = $pdo->prepare("
            INSERT INTO contributions
                (campaign_id, contributor_id, type, amount, note, status)
            VALUES
                (:cid, :uid, 'monetary', :amount, :note, 'completed')
        ");
        $stmt->execute([
            'cid' => $campaignId,
            'uid' => $user['id'],
            'amount' => $amount,
            'note' => $note,
        ]);
        $contribId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("SELECT current_amount FROM campaigns WHERE id = :id");
        $stmt->execute(['id' => $campaignId]);
        $newCampaignAmount = (float)$stmt->fetchColumn();

        $pdo->commit();

        return [
            'success' => true,
            'contribution_id' => $contribId,
            'type' => 'monetary',
            'amount' => $amount,
            'new_balance' => $newBalance,
            'campaign' => ['id' => $campaignId, 'current_amount' => $newCampaignAmount],
            'message' => sprintf('Donated %.2f', $amount),
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function createHours($pdo, $user, $campaignId, $input, $note) {
    $hours = $input['hours_count'] ?? null;
    if (!is_numeric($hours) || (float)$hours <= 0 || (float)$hours > 10000) {
        http_response_code(400);
        throw new Exception('hours_count must be a positive number (max 10000)');
    }
    $hours = round((float)$hours, 2);

    $stmt = $pdo->prepare("
        INSERT INTO contributions
            (campaign_id, contributor_id, type, hours_count, note, status)
        VALUES
            (:cid, :uid, 'hours', :hours, :note, 'pending')
    ");
    $stmt->execute([
        'cid' => $campaignId,
        'uid' => $user['id'],
        'hours' => $hours,
        'note' => $note,
    ]);

    return [
        'success' => true,
        'contribution_id' => (int)$pdo->lastInsertId(),
        'type' => 'hours',
        'hours_count' => $hours,
        'message' => sprintf('Signed up for %.2f hours', $hours),
    ];
}

function createGoods($pdo, $user, $campaignId, $input, $note) {
    $desc = isset($input['goods_description']) ? trim((string)$input['goods_description']) : '';
    if ($desc === '' || strlen($desc) > 1000) {
        http_response_code(400);
        throw new Exception('goods_description is required (max 1000 chars)');
    }

    $estValue = $input['goods_estimated_value'] ?? null;
    if ($estValue !== null && $estValue !== '') {
        if (!is_numeric($estValue) || (float)$estValue < 0) {
            http_response_code(400);
            throw new Exception('goods_estimated_value must be a non-negative number');
        }
        $estValue = round((float)$estValue, 2);
    } else {
        $estValue = null;
    }

    $stmt = $pdo->prepare("
        INSERT INTO contributions
            (campaign_id, contributor_id, type, goods_description, goods_estimated_value, note, status)
        VALUES
            (:cid, :uid, 'goods', :desc, :val, :note, 'pending')
    ");
    $stmt->execute([
        'cid' => $campaignId,
        'uid' => $user['id'],
        'desc' => $desc,
        'val' => $estValue,
        'note' => $note,
    ]);

    return [
        'success' => true,
        'contribution_id' => (int)$pdo->lastInsertId(),
        'type' => 'goods',
        'message' => 'Goods pledged',
    ];
}

function cancelContribution($id, $pdo) {
    $user = requireAuthUser($pdo);

    $stmt = $pdo->prepare("SELECT * FROM contributions WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $contrib = $stmt->fetch();
    if (!$contrib) {
        http_response_code(404);
        throw new Exception('Contribution not found');
    }
    if ((int)$contrib['contributor_id'] !== (int)$user['id']) {
        http_response_code(403);
        throw new Exception('You can only cancel your own contributions');
    }
    if ($contrib['type'] === 'monetary') {
        http_response_code(409);
        throw new Exception('Monetary donations cannot be cancelled');
    }
    if ($contrib['status'] !== 'pending') {
        http_response_code(409);
        throw new Exception('Only pending contributions can be cancelled');
    }

    $pdo->prepare("
        UPDATE contributions
        SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ")->execute(['id' => $id]);

    return ['success' => true, 'status' => 'cancelled', 'message' => 'Contribution cancelled'];
}

function optionalAuthUserId() {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $userId = $headers['X-User-Id'] ?? $_SERVER['HTTP_X_USER_ID'] ?? null;
    return $userId ? (int)$userId : null;
}

function requireAuthUser($pdo) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $userId = $headers['X-User-Id'] ?? $_SERVER['HTTP_X_USER_ID'] ?? null;
    if (!$userId) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }
    try {
        $stmt = $pdo->prepare("SELECT id, email, role, first_name, last_name FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) {
            http_response_code(401);
            throw new Exception('Authentication required');
        }
        return $user;
    } catch (PDOException $e) {
        error_log("contributions requireAuthUser: " . $e->getMessage());
        http_response_code(500);
        throw new Exception('Server error');
    }
}

function readJsonInput() {
    $raw = file_get_contents('php://input');
    $input = $raw ? json_decode($raw, true) : null;
    return $input ?: $_POST;
}
