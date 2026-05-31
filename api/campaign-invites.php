<?php
require_once __DIR__ . '/../backend/includes/config.php';

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
    $inviteId = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;
    $campaignId = isset($_GET['campaign_id']) && is_numeric($_GET['campaign_id']) ? (int)$_GET['campaign_id'] : null;
    $my = isset($_GET['my']) && $_GET['my'] === 'true';

    switch ($method) {
        case 'GET':
            if ($my) {
                $response = listMyInvites($pdo);
            } elseif ($campaignId) {
                $response = listInvitesForCampaign($campaignId, $pdo);
            } else {
                http_response_code(400);
                throw new Exception('Either my=true or campaign_id is required');
            }
            break;

        case 'POST':
            $response = createInvite($pdo);
            break;

        case 'PUT':
            if (!$inviteId) {
                http_response_code(400);
                throw new Exception('Invite id is required');
            }
            $response = decideInvite($inviteId, $pdo);
            break;

        case 'DELETE':
            if (!$inviteId) {
                http_response_code(400);
                throw new Exception('Invite id is required');
            }
            $response = revokeInvite($inviteId, $pdo);
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

function listMyInvites($pdo) {
    $user = requireAuthUser($pdo);

    $stmt = $pdo->prepare("
        SELECT i.*, c.title AS campaign_title, c.description AS campaign_description,
               c.campaign_type, c.status AS campaign_status,
               u.first_name AS inviter_first_name, u.last_name AS inviter_last_name
        FROM campaign_invites i
        JOIN campaigns c ON c.id = i.campaign_id
        JOIN users u ON u.id = i.invited_by
        WHERE i.invited_user_id = :uid
          AND i.status IN ('pending', 'accepted')
        ORDER BY
            CASE i.status WHEN 'pending' THEN 0 ELSE 1 END,
            i.created_at DESC
    ");
    $stmt->execute(['uid' => $user['id']]);
    return ['success' => true, 'invites' => $stmt->fetchAll()];
}

function listInvitesForCampaign($campaignId, $pdo) {
    $user = requireAuthUser($pdo);
    $campaign = fetchCampaignOrFail($campaignId, $pdo);

    if ((int)$campaign['organizer_id'] !== (int)$user['id']) {
        http_response_code(403);
        throw new Exception('Only the campaign organizer can view invites');
    }

    $stmt = $pdo->prepare("
        SELECT i.*, u.email, u.first_name, u.last_name, u.role
        FROM campaign_invites i
        JOIN users u ON u.id = i.invited_user_id
        WHERE i.campaign_id = :cid
        ORDER BY
            CASE i.status WHEN 'pending' THEN 0 WHEN 'accepted' THEN 1 ELSE 2 END,
            i.created_at DESC
    ");
    $stmt->execute(['cid' => $campaignId]);
    return ['success' => true, 'invites' => $stmt->fetchAll()];
}

function createInvite($pdo) {
    $user = requireAuthUser($pdo);
    $input = readJsonInput();

    $campaignId = isset($input['campaign_id']) ? (int)$input['campaign_id'] : 0;
    $email = isset($input['email']) ? strtolower(trim((string)$input['email'])) : '';
    $message = isset($input['message']) ? trim((string)$input['message']) : null;
    if ($message === '') $message = null;

    if ($campaignId <= 0) {
        http_response_code(400);
        throw new Exception('campaign_id is required');
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        throw new Exception('Valid email is required');
    }

    $campaign = fetchCampaignOrFail($campaignId, $pdo);
    if ((int)$campaign['organizer_id'] !== (int)$user['id']) {
        http_response_code(403);
        throw new Exception('Only the campaign organizer can send invites');
    }

    $stmt = $pdo->prepare("SELECT id, first_name, last_name, role FROM users WHERE LOWER(email) = :e");
    $stmt->execute(['e' => $email]);
    $invitee = $stmt->fetch();
    if (!$invitee) {
        http_response_code(404);
        throw new Exception('No registered user with that email');
    }
    if ((int)$invitee['id'] === (int)$user['id']) {
        http_response_code(400);
        throw new Exception('You cannot invite yourself');
    }

    $stmt = $pdo->prepare("
        SELECT id, status FROM campaign_invites
        WHERE campaign_id = :cid AND invited_user_id = :uid
    ");
    $stmt->execute(['cid' => $campaignId, 'uid' => $invitee['id']]);
    $existing = $stmt->fetch();

    if ($existing) {
        if (in_array($existing['status'], ['pending', 'accepted'], true)) {
            http_response_code(409);
            throw new Exception('That user already has an active invite');
        }
        $stmt = $pdo->prepare("
            UPDATE campaign_invites
            SET status = 'pending', message = :msg, invited_by = :inviter,
                decided_at = NULL, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $stmt->execute(['msg' => $message, 'inviter' => $user['id'], 'id' => $existing['id']]);
        return [
            'success' => true,
            'invite_id' => (int)$existing['id'],
            'invitee' => $invitee,
            'message' => 'Invite re-sent',
        ];
    }

    $stmt = $pdo->prepare("
        INSERT INTO campaign_invites
            (campaign_id, invited_user_id, invited_by, status, message)
        VALUES
            (:cid, :uid, :inviter, 'pending', :msg)
    ");
    $stmt->execute([
        'cid' => $campaignId,
        'uid' => $invitee['id'],
        'inviter' => $user['id'],
        'msg' => $message,
    ]);

    return [
        'success' => true,
        'invite_id' => (int)$pdo->lastInsertId(),
        'invitee' => $invitee,
        'message' => 'Invite sent',
    ];
}

function decideInvite($id, $pdo) {
    $user = requireAuthUser($pdo);
    $input = readJsonInput();
    $action = $input['action'] ?? '';
    if (!in_array($action, ['accept', 'decline'], true)) {
        http_response_code(400);
        throw new Exception('action must be accept or decline');
    }

    $stmt = $pdo->prepare("SELECT * FROM campaign_invites WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $invite = $stmt->fetch();
    if (!$invite) {
        http_response_code(404);
        throw new Exception('Invite not found');
    }
    if ((int)$invite['invited_user_id'] !== (int)$user['id']) {
        http_response_code(403);
        throw new Exception('Only the invitee can accept or decline this invite');
    }
    if ($invite['status'] !== 'pending') {
        http_response_code(409);
        throw new Exception('This invite has already been ' . $invite['status']);
    }

    $newStatus = $action === 'accept' ? 'accepted' : 'declined';
    $pdo->prepare("
        UPDATE campaign_invites
        SET status = :s, decided_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ")->execute(['s' => $newStatus, 'id' => $id]);

    return ['success' => true, 'status' => $newStatus, 'message' => 'Invite ' . $newStatus];
}

function revokeInvite($id, $pdo) {
    $user = requireAuthUser($pdo);

    $stmt = $pdo->prepare("
        SELECT i.*, c.organizer_id
        FROM campaign_invites i
        JOIN campaigns c ON c.id = i.campaign_id
        WHERE i.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $invite = $stmt->fetch();
    if (!$invite) {
        http_response_code(404);
        throw new Exception('Invite not found');
    }
    if ((int)$invite['organizer_id'] !== (int)$user['id']) {
        http_response_code(403);
        throw new Exception('Only the campaign organizer can revoke invites');
    }
    if ($invite['status'] === 'revoked') {
        return ['success' => true, 'status' => 'revoked', 'message' => 'Already revoked'];
    }

    $pdo->prepare("
        UPDATE campaign_invites
        SET status = 'revoked', decided_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ")->execute(['id' => $id]);

    return ['success' => true, 'status' => 'revoked', 'message' => 'Invite revoked'];
}

function fetchCampaignOrFail($id, $pdo) {
    $stmt = $pdo->prepare("SELECT id, organizer_id, status, visibility, title FROM campaigns WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $campaign = $stmt->fetch();
    if (!$campaign) {
        http_response_code(404);
        throw new Exception('Campaign not found');
    }
    return $campaign;
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
        error_log("invites requireAuthUser: " . $e->getMessage());
        http_response_code(500);
        throw new Exception('Server error');
    }
}

function readJsonInput() {
    $raw = file_get_contents('php://input');
    $input = $raw ? json_decode($raw, true) : null;
    return $input ?: $_POST;
}
