<?php
require_once __DIR__ . '/../backend/includes/config.php';
require_once __DIR__ . '/../backend/includes/campaign_access.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5500');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

const MAX_QUANTITY_PER_PURCHASE = 100;

$response = ['success' => false];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $response = listMyPurchases($pdo);
            break;

        case 'POST':
            $response = createPurchase($pdo);
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

function listMyPurchases($pdo) {
    $user = requireBuyer($pdo);

    $stmt = $pdo->prepare("
        SELECT p.*, i.name AS item_name, i.item_type, c.title AS campaign_title
        FROM purchases p
        JOIN campaign_items i ON i.id = p.item_id
        JOIN campaigns c ON c.id = p.campaign_id
        WHERE p.buyer_id = :uid
        ORDER BY p.created_at DESC
        LIMIT 100
    ");
    $stmt->execute(['uid' => $user['id']]);
    return ['success' => true, 'purchases' => $stmt->fetchAll()];
}

function createPurchase($pdo) {
    $user = requireBuyer($pdo);
    $input = readJson();

    $itemId = isset($input['item_id']) ? (int)$input['item_id'] : 0;
    $quantity = isset($input['quantity']) ? (int)$input['quantity'] : 0;

    if ($itemId <= 0) {
        http_response_code(400);
        throw new Exception('item_id is required');
    }
    if ($quantity <= 0 || $quantity > MAX_QUANTITY_PER_PURCHASE) {
        http_response_code(400);
        throw new Exception('Quantity must be between 1 and ' . MAX_QUANTITY_PER_PURCHASE);
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            SELECT i.id, i.campaign_id, i.production_cost, i.donation_amount,
                   i.quantity_available, i.quantity_sold, i.status,
                   c.id AS campaign_pk, c.organizer_id, c.status AS campaign_status, c.visibility
            FROM campaign_items i
            JOIN campaigns c ON c.id = i.campaign_id
            WHERE i.id = :id
        ");
        $stmt->execute(['id' => $itemId]);
        $item = $stmt->fetch();

        if (!$item) {
            http_response_code(404);
            throw new Exception('Item not found');
        }
        if ($item['status'] !== 'active') {
            http_response_code(409);
            throw new Exception('Item is not available for purchase');
        }

        $campaignForAccess = [
            'id' => (int)$item['campaign_pk'],
            'organizer_id' => $item['organizer_id'],
            'status' => $item['campaign_status'],
            'visibility' => $item['visibility'],
        ];
        if (!can_act_on_campaign($campaignForAccess, (int)$user['id'], $pdo)) {
            if ((int)$item['organizer_id'] === (int)$user['id']) {
                http_response_code(403);
                throw new Exception('Organizers cannot purchase items from their own campaign');
            }
            if ($item['campaign_status'] !== 'published') {
                http_response_code(409);
                throw new Exception('This campaign is not currently accepting purchases');
            }
            http_response_code(403);
            throw new Exception('You need an accepted invitation to purchase from this private campaign');
        }

        $qtyAvailable = (int)$item['quantity_available'];
        $qtySold = (int)$item['quantity_sold'];
        $remaining = $qtyAvailable === -1 ? PHP_INT_MAX : ($qtyAvailable - $qtySold);

        if ($quantity > $remaining) {
            http_response_code(409);
            throw new Exception("Only $remaining item(s) remaining");
        }

        $unitCost = (float)$item['production_cost'];
        $unitDonation = (float)$item['donation_amount'];
        $totalCost = $unitCost * $quantity;
        $totalDonation = $unitDonation * $quantity;
        $totalPaid = $totalCost + $totalDonation;

        $stmt = $pdo->prepare("SELECT virtual_balance FROM users WHERE id = :id");
        $stmt->execute(['id' => $user['id']]);
        $balance = (float)$stmt->fetchColumn();

        if ($balance < $totalPaid) {
            http_response_code(402);
            throw new Exception(sprintf(
                'Insufficient virtual balance: need %.2f, have %.2f',
                $totalPaid, $balance
            ));
        }

        $newBalance = $balance - $totalPaid;
        $stmt = $pdo->prepare("UPDATE users SET virtual_balance = :b WHERE id = :id");
        $stmt->execute(['b' => $newBalance, 'id' => $user['id']]);

        $newSold = $qtySold + $quantity;
        $newStatus = ($qtyAvailable !== -1 && $newSold >= $qtyAvailable) ? 'sold_out' : 'active';
        $stmt = $pdo->prepare("
            UPDATE campaign_items
            SET quantity_sold = :sold, status = :status, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $stmt->execute(['sold' => $newSold, 'status' => $newStatus, 'id' => $itemId]);

        $stmt = $pdo->prepare("
            UPDATE campaigns
            SET current_amount = current_amount + :d, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $stmt->execute(['d' => $totalDonation, 'id' => $item['campaign_id']]);

        $stmt = $pdo->prepare("
            INSERT INTO purchases
                (item_id, buyer_id, campaign_id, quantity, unit_cost, unit_donation, total_paid, total_donation)
            VALUES
                (:item_id, :buyer_id, :campaign_id, :qty, :uc, :ud, :tp, :td)
        ");
        $stmt->execute([
            'item_id' => $itemId,
            'buyer_id' => $user['id'],
            'campaign_id' => $item['campaign_id'],
            'qty' => $quantity,
            'uc' => $unitCost,
            'ud' => $unitDonation,
            'tp' => $totalPaid,
            'td' => $totalDonation,
        ]);
        $purchaseId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("SELECT current_amount FROM campaigns WHERE id = :id");
        $stmt->execute(['id' => $item['campaign_id']]);
        $newCampaignAmount = (float)$stmt->fetchColumn();

        $pdo->commit();

        return [
            'success' => true,
            'purchase_id' => $purchaseId,
            'total_paid' => $totalPaid,
            'total_donation' => $totalDonation,
            'new_balance' => $newBalance,
            'item' => [
                'id' => $itemId,
                'quantity_sold' => $newSold,
                'status' => $newStatus,
            ],
            'campaign' => [
                'id' => (int)$item['campaign_id'],
                'current_amount' => $newCampaignAmount,
            ],
            'message' => 'Purchase complete',
        ];

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function requireBuyer($pdo) {
    $user = getCurrentUserFromHeader($pdo);
    if (!$user) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }
    if (!in_array($user['role'], ['volunteer', 'company'], true)) {
        http_response_code(403);
        throw new Exception('Only volunteers and companies can purchase items');
    }
    return $user;
}

function getCurrentUserFromHeader($pdo) {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $userId = $headers['X-User-Id'] ?? $_SERVER['HTTP_X_USER_ID'] ?? null;
    if (!$userId) return null;

    try {
        $stmt = $pdo->prepare("SELECT id, email, role, first_name, last_name, virtual_balance FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log("purchases getCurrentUserFromHeader: " . $e->getMessage());
        return null;
    }
}

function readJson() {
    $raw = file_get_contents('php://input');
    $input = $raw ? json_decode($raw, true) : null;
    return $input ?: $_POST;
}
