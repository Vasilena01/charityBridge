<?php
require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/includes/auth.php';
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
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uriParts = explode('/', trim($uri, '/'));
    $campaignId = isset($uriParts[2]) && is_numeric($uriParts[2]) ? (int)$uriParts[2] : null;

    if (!$campaignId && isset($_GET['id']) && is_numeric($_GET['id'])) {
        $campaignId = (int)$_GET['id'];
    }

    switch ($method) {
        case 'GET':
            if ($campaignId) {
                $response = getCampaign($campaignId, $pdo);
            } elseif (isset($_GET['my']) && $_GET['my'] === 'true') {
                $response = getMyCampaigns($pdo);
            } else {
                $response = getAllCampaigns($pdo);
            }
            break;

        case 'POST':
            $response = createCampaign($pdo);
            break;

        case 'PUT':
            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }
            $response = updateCampaign($campaignId, $pdo);
            break;

        case 'DELETE':
            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }
            $response = deleteCampaign($campaignId, $pdo);
            break;

        default:
            http_response_code(405);
            throw new Exception('Method not allowed');
    }

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    $response['error'] = $e->getMessage();
}

echo json_encode($response);

function getAllCampaigns($pdo) {
    $params = [];
    $viewer = getCurrentUser($pdo);
    $viewerId = $viewer ? (int)$viewer['id'] : null;

    $where = ["c.status = 'published'"];

    if (isset($_GET['type']) && !empty($_GET['type'])) {
        $where[] = "c.campaign_type = :type";
        $params['type'] = $_GET['type'];
    }

    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where[] = "(c.title LIKE :search OR c.description LIKE :search)";
        $params['search'] = '%' . $_GET['search'] . '%';
    }

    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $where[] = "c.status = :status";
        $params['status'] = $_GET['status'];
    }

    $where[] = campaign_visibility_sql(':viewer_id');
    $params['viewer_id'] = $viewerId;

    $whereClause = implode(' AND ', $where);

    $sql = "
        SELECT c.*, u.first_name, u.last_name
        FROM campaigns c
        JOIN users u ON c.organizer_id = u.id
        WHERE $whereClause
        ORDER BY c.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return [
        'success' => true,
        'campaigns' => $stmt->fetchAll()
    ];
}

function getCampaign($id, $pdo) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.first_name, u.last_name, u.email as organizer_email
        FROM campaigns c
        JOIN users u ON c.organizer_id = u.id
        WHERE c.id = :id
    ");
    $stmt->execute(['id' => $id]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        http_response_code(404);
        throw new Exception('Campaign not found');
    }

    $user = getCurrentUser($pdo);
    $userId = $user ? (int)$user['id'] : null;

    if (!can_view_campaign($campaign, $userId, $pdo)) {
        http_response_code(403);
        if ($campaign['status'] === 'draft') {
            throw new Exception('This campaign is not yet published');
        }
        throw new Exception('This campaign is private');
    }

    $campaign['viewer_invite_status'] = campaign_invite_status($campaign['id'], $userId, $pdo);
    $campaign['viewer_is_owner'] = $userId && (int)$campaign['organizer_id'] === $userId;

    return [
        'success' => true,
        'campaign' => $campaign
    ];
}

function getMyCampaigns($pdo) {
    $user = getCurrentUser($pdo);

    if (!$user) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }

    if ($user['role'] !== 'organizer') {
        http_response_code(403);
        throw new Exception('Only organizers can create campaigns');
    }

    $stmt = $pdo->prepare("
        SELECT * FROM campaigns
        WHERE organizer_id = :organizer_id
        ORDER BY created_at DESC
    ");
    $stmt->execute(['organizer_id' => $user['id']]);

    return [
        'success' => true,
        'campaigns' => $stmt->fetchAll()
    ];
}

function createCampaign($pdo) {
    $user = getCurrentUser($pdo);

    if (!$user) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }

    if ($user['role'] !== 'organizer') {
        http_response_code(403);
        throw new Exception('Only organizers can create campaigns');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $required = ['title', 'description', 'campaign_type', 'goal_amount', 'deadline'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception(ucfirst($field) . ' is required');
        }
    }

    if (!is_numeric($input['goal_amount']) || $input['goal_amount'] <= 0) {
        throw new Exception('Goal amount must be a positive number');
    }

    $deadline = strtotime($input['deadline']);
    if (!$deadline || $deadline <= time()) {
        throw new Exception('Deadline must be in the future');
    }

    $status = isset($input['publish']) && $input['publish'] ? 'published' : 'draft';
    $visibility = $input['visibility'] ?? 'public';

    $stmt = $pdo->prepare("
        INSERT INTO campaigns (organizer_id, title, description, campaign_type, goal_amount, deadline, status, visibility)
        VALUES (:organizer_id, :title, :description, :campaign_type, :goal_amount, :deadline, :status, :visibility)
    ");

    $stmt->execute([
        'organizer_id' => $user['id'],
        'title' => trim($input['title']),
        'description' => trim($input['description']),
        'campaign_type' => $input['campaign_type'],
        'goal_amount' => $input['goal_amount'],
        'deadline' => date('Y-m-d H:i:s', $deadline),
        'status' => $status,
        'visibility' => $visibility
    ]);

    return [
        'success' => true,
        'campaign_id' => $pdo->lastInsertId(),
        'message' => 'Campaign created successfully'
    ];
}

function updateCampaign($id, $pdo) {
    $user = getCurrentUser($pdo);

    if (!$user) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }

    $stmt = $pdo->prepare("SELECT organizer_id FROM campaigns WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        http_response_code(404);
        throw new Exception('Campaign not found');
    }

    if ($campaign['organizer_id'] != $user['id']) {
        http_response_code(403);
        throw new Exception('You can only edit your own campaigns');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $updates = [];
    $params = ['id' => $id];

    $allowedFields = ['title', 'description', 'campaign_type', 'goal_amount', 'deadline', 'status', 'visibility'];
    foreach ($allowedFields as $field) {
        if (isset($input[$field])) {
            $updates[] = "$field = :$field";
            $params[$field] = $field === 'deadline' ? date('Y-m-d H:i:s', strtotime($input[$field])) : $input[$field];
        }
    }

    if (empty($updates)) {
        throw new Exception('No fields to update');
    }

    $updates[] = "updated_at = CURRENT_TIMESTAMP";
    $sql = "UPDATE campaigns SET " . implode(', ', $updates) . " WHERE id = :id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return [
        'success' => true,
        'message' => 'Campaign updated successfully'
    ];
}

function deleteCampaign($id, $pdo) {
    $user = getCurrentUser($pdo);

    if (!$user) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }

    $stmt = $pdo->prepare("SELECT organizer_id FROM campaigns WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $campaign = $stmt->fetch();

    if (!$campaign) {
        http_response_code(404);
        throw new Exception('Campaign not found');
    }

    if ($campaign['organizer_id'] != $user['id']) {
        http_response_code(403);
        throw new Exception('You can only delete your own campaigns');
    }

    $stmt = $pdo->prepare("DELETE FROM campaigns WHERE id = :id");
    $stmt->execute(['id' => $id]);

    return [
        'success' => true,
        'message' => 'Campaign deleted successfully'
    ];
}

function getCurrentUser($pdo) {
    $headers = getallheaders();
    $userId = $headers['X-User-Id'] ?? $_SERVER['HTTP_X_USER_ID'] ?? null;

    if (!$userId) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT id, email, role, first_name, last_name FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}
