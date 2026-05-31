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
    $offerId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
    $campaignId = isset($_GET['campaign_id']) && is_numeric($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : null;
    $my = isset($_GET['my']) && $_GET['my'] === 'true';

    switch ($method) {
        case 'GET':
            if ($my) {
                $response = listMyOffers($pdo);
            } elseif ($campaignId) {
                $response = listOffersForCampaign($campaignId, $pdo);
            } else {
                http_response_code(400);
                throw new Exception('Either my=true or campaign_id is required');
            }
            break;

        case 'POST':
            $response = createOffer($pdo);
            break;

        case 'PUT':
            if (!$offerId) {
                http_response_code(400);
                throw new Exception('Offer id is required');
            }
            $response = decideOffer($offerId, $pdo);
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

function listMyOffers($pdo) {
    $user = requireAuthUser($pdo);

    $stmt = $pdo->prepare("
        SELECT o.*, c.title AS campaign_title
        FROM production_offers o
        JOIN campaigns c ON c.id = o.campaign_id
        WHERE o.producer_id = :uid
        ORDER BY o.created_at DESC
    ");
    $stmt->execute(['uid' => $user['id']]);
    return ['success' => true, 'offers' => $stmt->fetchAll()];
}

function listOffersForCampaign($campaignId, $pdo) {
    $user = requireAuthUser($pdo);

    $stmt = $pdo->prepare("SELECT id, organizer_id, title FROM campaigns WHERE id = :id");
    $stmt->execute(['id' => $campaignId]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        http_response_code(404);
        throw new Exception('Campaign not found');
    }
    if ((int)$campaign['organizer_id'] !== (int)$user['id']) {
        http_response_code(403);
        throw new Exception('You can only view offers on your own campaigns');
    }

    $stmt = $pdo->prepare("
        SELECT o.*, u.first_name, u.last_name, u.email AS producer_email, u.role AS producer_role
        FROM production_offers o
        JOIN users u ON u.id = o.producer_id
        WHERE o.campaign_id = :cid
        ORDER BY
            CASE o.status WHEN 'pending' THEN 0 ELSE 1 END,
            o.created_at DESC
    ");
    $stmt->execute(['cid' => $campaignId]);
    return ['success' => true, 'offers' => $stmt->fetchAll()];
}

function createOffer($pdo) {
    $user = requireAuthUser($pdo);

    if (!in_array($user['role'], ['volunteer', 'company'], true)) {
        http_response_code(403);
        throw new Exception('Only volunteers and companies can offer to produce items');
    }

    $input = readJsonInput();

    $campaignId = isset($input['campaign_id']) ? (int)$input['campaign_id'] : 0;
    if ($campaignId <= 0) {
        http_response_code(400);
        throw new Exception('campaign_id is required');
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
            throw new Exception('You cannot offer to produce for your own campaign');
        }
        if ($campaign['status'] !== 'published') {
            http_response_code(409);
            throw new Exception('Cannot offer to produce for an unpublished campaign');
        }
        http_response_code(403);
        throw new Exception('You need an accepted invitation to offer production for this private campaign');
    }

    $fields = validateOfferFields($input);

    $stmt = $pdo->prepare("
        INSERT INTO production_offers
            (campaign_id, producer_id, name, description, item_type,
             proposed_production_cost, proposed_donation_amount, quantity_offered, status)
        VALUES
            (:campaign_id, :producer_id, :name, :description, :item_type,
             :cost, :donation, :qty, 'pending')
    ");
    $stmt->execute([
        'campaign_id' => $campaignId,
        'producer_id' => $user['id'],
        'name' => $fields['name'],
        'description' => $fields['description'],
        'item_type' => $fields['item_type'],
        'cost' => $fields['proposed_production_cost'],
        'donation' => $fields['proposed_donation_amount'],
        'qty' => $fields['quantity_offered'],
    ]);

    return [
        'success' => true,
        'offer_id' => (int)$pdo->lastInsertId(),
        'message' => 'Offer submitted',
    ];
}

function decideOffer($offerId, $pdo) {
    $user = requireAuthUser($pdo);
    $input = readJsonInput();

    $action = $input['action'] ?? '';
    if (!in_array($action, ['accept', 'reject', 'cancel'], true)) {
        http_response_code(400);
        throw new Exception('action must be accept, reject, or cancel');
    }

    $stmt = $pdo->prepare("
        SELECT o.*, c.organizer_id, c.status AS campaign_status
        FROM production_offers o
        JOIN campaigns c ON c.id = o.campaign_id
        WHERE o.id = :id
    ");
    $stmt->execute(['id' => $offerId]);
    $offer = $stmt->fetch();

    if (!$offer) {
        http_response_code(404);
        throw new Exception('Offer not found');
    }
    if ($offer['status'] !== 'pending') {
        http_response_code(409);
        throw new Exception('This offer has already been decided');
    }

    if ($action === 'cancel') {
        if ((int)$offer['producer_id'] !== (int)$user['id']) {
            http_response_code(403);
            throw new Exception('Only the producer can cancel their offer');
        }
        $pdo->prepare("
            UPDATE production_offers
            SET status = 'cancelled', decided_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ")->execute(['id' => $offerId]);
        return ['success' => true, 'status' => 'cancelled', 'message' => 'Offer cancelled'];
    }

    if ((int)$offer['organizer_id'] !== (int)$user['id']) {
        http_response_code(403);
        throw new Exception('Only the campaign organizer can decide this offer');
    }

    if ($action === 'reject') {
        $note = isset($input['organizer_note']) ? trim((string)$input['organizer_note']) : null;
        $pdo->prepare("
            UPDATE production_offers
            SET status = 'rejected', organizer_note = :note,
                decided_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ")->execute(['id' => $offerId, 'note' => $note ?: null]);
        return ['success' => true, 'status' => 'rejected', 'message' => 'Offer rejected'];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO campaign_items
                (campaign_id, producer_id, name, description, item_type,
                 production_cost, donation_amount, quantity_available, status)
            VALUES
                (:campaign_id, :producer_id, :name, :description, :item_type,
                 :cost, :donation, :qty, 'active')
        ");
        $stmt->execute([
            'campaign_id' => $offer['campaign_id'],
            'producer_id' => $offer['producer_id'],
            'name' => $offer['name'],
            'description' => $offer['description'],
            'item_type' => $offer['item_type'],
            'cost' => $offer['proposed_production_cost'],
            'donation' => $offer['proposed_donation_amount'],
            'qty' => $offer['quantity_offered'],
        ]);
        $itemId = (int)$pdo->lastInsertId();

        $pdo->prepare("
            UPDATE production_offers
            SET status = 'accepted',
                accepted_item_id = :item_id,
                decided_at = CURRENT_TIMESTAMP,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ")->execute(['item_id' => $itemId, 'id' => $offerId]);

        $pdo->commit();

        return [
            'success' => true,
            'status' => 'accepted',
            'item_id' => $itemId,
            'message' => 'Offer accepted',
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function validateOfferFields($input) {
    $name = trim((string)($input['name'] ?? ''));
    if ($name === '' || strlen($name) > 255) {
        throw new Exception('Item name is required (max 255 chars)');
    }

    $desc = isset($input['description']) ? trim((string)$input['description']) : '';
    $type = $input['item_type'] ?? 'good';
    if (!in_array($type, ['good', 'service'], true)) {
        throw new Exception('item_type must be "good" or "service"');
    }

    $cost = $input['proposed_production_cost'] ?? null;
    if (!is_numeric($cost) || (float)$cost < 0) {
        throw new Exception('proposed_production_cost must be a non-negative number');
    }
    $donation = $input['proposed_donation_amount'] ?? null;
    if (!is_numeric($donation) || (float)$donation < 0) {
        throw new Exception('proposed_donation_amount must be a non-negative number');
    }
    if ((float)$cost + (float)$donation <= 0) {
        throw new Exception('Total price (cost + donation) must be greater than zero');
    }

    $qty = $input['quantity_offered'] ?? 1;
    if (!is_numeric($qty) || (int)$qty < 1 || (int)$qty > 1000) {
        throw new Exception('quantity_offered must be between 1 and 1000');
    }

    return [
        'name' => $name,
        'description' => $desc === '' ? null : $desc,
        'item_type' => $type,
        'proposed_production_cost' => number_format((float)$cost, 2, '.', ''),
        'proposed_donation_amount' => number_format((float)$donation, 2, '.', ''),
        'quantity_offered' => (int)$qty,
    ];
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
        error_log("production-offers requireAuthUser: " . $e->getMessage());
        http_response_code(500);
        throw new Exception('Server error');
    }
}

function readJsonInput() {
    $raw = file_get_contents('php://input');
    $input = $raw ? json_decode($raw, true) : null;
    return $input ?: $_POST;
}
