<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/includes/campaign_access.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5500');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false];
$method = $_SERVER['REQUEST_METHOD'];

try {
    $itemId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
    $campaignId = isset($_GET['campaign_id']) && is_numeric($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : null;

    switch ($method) {
        case 'GET':
            if ($itemId) {
                $response = getItem($itemId, $pdo);
            } elseif ($campaignId) {
                $response = listItems($campaignId, $pdo);
            } else {
                http_response_code(400);
                throw new Exception('Either id or campaign_id is required');
            }
            break;

        case 'POST':
            $response = createItem($pdo);
            break;

        case 'PUT':
            if (!$itemId) {
                http_response_code(400);
                throw new Exception('Item id is required');
            }
            $response = updateItem($itemId, $pdo);
            break;

        case 'DELETE':
            if (!$itemId) {
                http_response_code(400);
                throw new Exception('Item id is required');
            }
            $response = deleteItem($itemId, $pdo);
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

function listItems($campaignId, $pdo) {
    $campaign = fetchCampaign($campaignId, $pdo);
    if (!$campaign) {
        http_response_code(404);
        throw new Exception('Campaign not found');
    }

    $user = getCurrentUserItems($pdo);
    $userId = $user ? (int)$user['id'] : null;

    if (!can_view_campaign($campaign, $userId, $pdo)) {
        http_response_code(403);
        throw new Exception($campaign['status'] === 'draft'
            ? 'This campaign is not yet published'
            : 'This campaign is private');
    }

    $stmt = $pdo->prepare(
        "SELECT * FROM campaign_items
         WHERE campaign_id = :cid
           AND status != 'removed'
         ORDER BY created_at ASC"
    );
    $stmt->execute(['cid' => $campaignId]);
    $items = $stmt->fetchAll();

    foreach ($items as &$item) {
        $item['total_price'] = number_format((float)$item['production_cost'] + (float)$item['donation_amount'], 2, '.', '');
    }

    return ['success' => true, 'items' => $items];
}

function getItem($id, $pdo) {
    $stmt = $pdo->prepare("SELECT * FROM campaign_items WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $item = $stmt->fetch();

    if (!$item) {
        http_response_code(404);
        throw new Exception('Item not found');
    }

    $campaign = fetchCampaign((int)$item['campaign_id'], $pdo);
    $user = getCurrentUserItems($pdo);
    $userId = $user ? (int)$user['id'] : null;

    if (!can_view_campaign($campaign, $userId, $pdo)) {
        http_response_code(403);
        throw new Exception($campaign['status'] === 'draft'
            ? 'This item belongs to an unpublished campaign'
            : 'This item belongs to a private campaign');
    }

    $item['total_price'] = number_format((float)$item['production_cost'] + (float)$item['donation_amount'], 2, '.', '');

    return ['success' => true, 'item' => $item];
}

function createItem($pdo) {
    $user = requireOrganizer($pdo);
    $input = readJsonInput();

    $campaignId = isset($input['campaign_id']) ? (int)$input['campaign_id'] : 0;
    if ($campaignId <= 0) {
        throw new Exception('campaign_id is required');
    }

    $campaign = fetchCampaign($campaignId, $pdo);
    if (!$campaign) {
        http_response_code(404);
        throw new Exception('Campaign not found');
    }
    if ((int)$campaign['organizer_id'] !== (int)$user['id']) {
        http_response_code(403);
        throw new Exception('You can only add items to your own campaigns');
    }

    $fields = validateItemFields($input, true);

    $stmt = $pdo->prepare(
        "INSERT INTO campaign_items
            (campaign_id, name, description, item_type, production_cost, donation_amount, quantity_available, status)
         VALUES
            (:campaign_id, :name, :description, :item_type, :production_cost, :donation_amount, :quantity_available, 'active')"
    );
    $stmt->execute([
        'campaign_id' => $campaignId,
        'name' => $fields['name'],
        'description' => $fields['description'],
        'item_type' => $fields['item_type'],
        'production_cost' => $fields['production_cost'],
        'donation_amount' => $fields['donation_amount'],
        'quantity_available' => $fields['quantity_available'],
    ]);

    return [
        'success' => true,
        'item_id' => (int)$pdo->lastInsertId(),
        'message' => 'Item added',
    ];
}

function updateItem($id, $pdo) {
    $user = requireOrganizer($pdo);

    $stmt = $pdo->prepare(
        "SELECT i.*, c.organizer_id
         FROM campaign_items i
         JOIN campaigns c ON c.id = i.campaign_id
         WHERE i.id = :id"
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        throw new Exception('Item not found');
    }
    if ((int)$row['organizer_id'] !== (int)$user['id']) {
        http_response_code(403);
        throw new Exception('You can only edit items on your own campaigns');
    }

    $input = readJsonInput();
    $fields = validateItemFields($input, false);

    $sets = [];
    $params = ['id' => $id];

    $assignable = ['name', 'description', 'item_type', 'production_cost', 'donation_amount', 'quantity_available', 'status'];
    foreach ($assignable as $f) {
        if (array_key_exists($f, $fields)) {
            $sets[] = "$f = :$f";
            $params[$f] = $fields[$f];
        }
    }

    if (empty($sets)) {
        throw new Exception('No fields to update');
    }

    $sets[] = "updated_at = CURRENT_TIMESTAMP";
    $sql = "UPDATE campaign_items SET " . implode(', ', $sets) . " WHERE id = :id";
    $pdo->prepare($sql)->execute($params);

    return ['success' => true, 'message' => 'Item updated'];
}

function deleteItem($id, $pdo) {
    $user = requireOrganizer($pdo);

    $stmt = $pdo->prepare(
        "SELECT c.organizer_id
         FROM campaign_items i
         JOIN campaigns c ON c.id = i.campaign_id
         WHERE i.id = :id"
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        http_response_code(404);
        throw new Exception('Item not found');
    }
    if ((int)$row['organizer_id'] !== (int)$user['id']) {
        http_response_code(403);
        throw new Exception('You can only delete items on your own campaigns');
    }

    $pdo->prepare("DELETE FROM campaign_items WHERE id = :id")->execute(['id' => $id]);

    return ['success' => true, 'message' => 'Item deleted'];
}

function readJsonInput() {
    $raw = file_get_contents('php://input');
    $input = $raw ? json_decode($raw, true) : null;
    return $input ?: $_POST;
}

function fetchCampaign($id, $pdo) {
    $stmt = $pdo->prepare("SELECT id, organizer_id, status, visibility FROM campaigns WHERE id = :id");
    $stmt->execute(['id' => $id]);
    return $stmt->fetch();
}

function requireOrganizer($pdo) {
    $user = getCurrentUserItems($pdo);
    if (!$user) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }
    if ($user['role'] !== 'organizer') {
        http_response_code(403);
        throw new Exception('Only organizers can manage campaign items');
    }
    return $user;
}

function getCurrentUserItems($pdo) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $userId = $headers['X-User-Id'] ?? $_SERVER['HTTP_X_USER_ID'] ?? null;
    if (!$userId) return null;

    try {
        $stmt = $pdo->prepare("SELECT id, email, role, first_name, last_name FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log("items getCurrentUserItems: " . $e->getMessage());
        return null;
    }
}

function validateItemFields($input, $strict) {
    $out = [];

    if ($strict || array_key_exists('name', $input)) {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '' || strlen($name) > 255) {
            throw new Exception('Item name is required (max 255 chars)');
        }
        $out['name'] = $name;
    }

    if (array_key_exists('description', $input)) {
        $desc = trim((string)$input['description']);
        $out['description'] = $desc === '' ? null : $desc;
    }

    if ($strict || array_key_exists('item_type', $input)) {
        $type = $input['item_type'] ?? 'good';
        if (!in_array($type, ['good', 'service'], true)) {
            throw new Exception('item_type must be "good" or "service"');
        }
        $out['item_type'] = $type;
    }

    if ($strict || array_key_exists('production_cost', $input)) {
        $cost = $input['production_cost'] ?? null;
        if (!is_numeric($cost) || (float)$cost < 0) {
            throw new Exception('production_cost must be a non-negative number');
        }
        $out['production_cost'] = number_format((float)$cost, 2, '.', '');
    }

    if ($strict || array_key_exists('donation_amount', $input)) {
        $don = $input['donation_amount'] ?? null;
        if (!is_numeric($don) || (float)$don < 0) {
            throw new Exception('donation_amount must be a non-negative number');
        }
        $out['donation_amount'] = number_format((float)$don, 2, '.', '');
    }

    if (isset($out['production_cost']) && isset($out['donation_amount'])) {
        if ((float)$out['production_cost'] + (float)$out['donation_amount'] <= 0) {
            throw new Exception('Total price (production cost + donation) must be greater than zero');
        }
    }

    if ($strict || array_key_exists('quantity_available', $input)) {
        $qty = $input['quantity_available'] ?? 1;
        if (!is_numeric($qty) || (int)$qty < -1) {
            throw new Exception('quantity_available must be -1 (unlimited) or a non-negative integer');
        }
        $out['quantity_available'] = (int)$qty;
    }

    if (array_key_exists('status', $input)) {
        $status = $input['status'];
        if (!in_array($status, ['active', 'sold_out', 'removed'], true)) {
            throw new Exception('Invalid status');
        }
        $out['status'] = $status;
    }

    return $out;
}
