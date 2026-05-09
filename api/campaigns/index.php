<?php
/**
 * Campaign API Endpoints
 * Handles CRUD operations for campaigns with role-based authorization
 */

require_once __DIR__ . '/../../backend/includes/config.php';
require_once __DIR__ . '/../../backend/includes/auth.php';

// Set JSON header
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5500');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id');
header('Access-Control-Allow-Credentials: true');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$response = ['success' => false];
$method = $_SERVER['REQUEST_METHOD'];

try {
    // Get request URI and parse ID if present
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uriParts = explode('/', trim($uri, '/'));
    $campaignId = isset($uriParts[2]) && is_numeric($uriParts[2]) ? (int)$uriParts[2] : null;

    // Also check for ID in query parameter
    if (!$campaignId && isset($_GET['id']) && is_numeric($_GET['id'])) {
        $campaignId = (int)$_GET['id'];
    }

    // Route based on HTTP method
    switch ($method) {
        case 'GET':
            if ($campaignId) {
                // GET /api/campaigns/{id} or /api/campaigns?id={id} - Get single campaign
                $response = getCampaign($campaignId, $pdo);
            } elseif (isset($_GET['my']) && $_GET['my'] === 'true') {
                // GET /api/campaigns?my=true - Get current user's campaigns
                $response = getMyCampaigns($pdo);
            } else {
                // GET /api/campaigns - Get all campaigns (with filters)
                $response = getAllCampaigns($pdo);
            }
            break;

        case 'POST':
            // POST /api/campaigns - Create new campaign (organizer only)
            $response = createCampaign($pdo);
            break;

        case 'PUT':
            // PUT /api/campaigns/{id} - Update campaign (owner only)
            if (!$campaignId) {
                throw new Exception('Campaign ID is required');
            }
            $response = updateCampaign($campaignId, $pdo);
            break;

        case 'DELETE':
            // DELETE /api/campaigns/{id} - Delete campaign (owner only)
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

/**
 * Get all campaigns with optional filters
 */
function getAllCampaigns($pdo) {
    $filters = [];
    $params = [];

    // Build WHERE clause based on query parameters
    $where = ["status = 'published'"]; // Only show published campaigns by default

    if (isset($_GET['type']) && !empty($_GET['type'])) {
        $where[] = "campaign_type = :type";
        $params['type'] = $_GET['type'];
    }

    if (isset($_GET['search']) && !empty($_GET['search'])) {
        $where[] = "(title LIKE :search OR description LIKE :search)";
        $params['search'] = '%' . $_GET['search'] . '%';
    }

    if (isset($_GET['status']) && !empty($_GET['status'])) {
        $where[] = "status = :status";
        $params['status'] = $_GET['status'];
    }

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
    $campaigns = $stmt->fetchAll();

    return [
        'success' => true,
        'campaigns' => $campaigns
    ];
}

/**
 * Get single campaign by ID
 */
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

    // Draft campaigns - only owner can see
    if ($campaign['status'] === 'draft') {
        if (!$user || $user['id'] != $campaign['organizer_id']) {
            http_response_code(403);
            throw new Exception('This campaign is not yet published');
        }
    }

    // All published campaigns are public - everyone can see
    return [
        'success' => true,
        'campaign' => $campaign
    ];
}

/**
 * Get current user's campaigns (organizer only)
 */
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
    $campaigns = $stmt->fetchAll();

    return [
        'success' => true,
        'campaigns' => $campaigns
    ];
}

/**
 * Create new campaign (organizer only)
 */
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

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    // Validate required fields
    $required = ['title', 'description', 'campaign_type', 'goal_amount', 'deadline'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            throw new Exception(ucfirst($field) . ' is required');
        }
    }

    // Validate goal amount
    if (!is_numeric($input['goal_amount']) || $input['goal_amount'] <= 0) {
        throw new Exception('Goal amount must be a positive number');
    }

    // Validate deadline
    $deadline = strtotime($input['deadline']);
    if (!$deadline || $deadline <= time()) {
        throw new Exception('Deadline must be in the future');
    }

    // Set status and visibility
    $status = isset($input['publish']) && $input['publish'] ? 'published' : 'draft';
    $visibility = $input['visibility'] ?? 'public';

    // Insert campaign
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

    $campaignId = $pdo->lastInsertId();

    return [
        'success' => true,
        'campaign_id' => $campaignId,
        'message' => 'Campaign created successfully'
    ];
}

/**
 * Update campaign (owner only)
 */
function updateCampaign($id, $pdo) {
    $user = getCurrentUser($pdo);

    if (!$user) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }

    // Check ownership
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

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    // Build update query dynamically
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

/**
 * Delete campaign (owner only)
 */
function deleteCampaign($id, $pdo) {
    $user = getCurrentUser($pdo);

    if (!$user) {
        http_response_code(401);
        throw new Exception('Authentication required');
    }

    // Check ownership
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

/**
 * Get current authenticated user from request
 * Simple approach: read user_id from X-User-Id header sent by frontend
 */
function getCurrentUser($pdo) {
    // Get user ID from custom header
    $headers = getallheaders();
    $userId = $headers['X-User-Id'] ?? $_SERVER['HTTP_X_USER_ID'] ?? null;

    if (!$userId) {
        return null;
    }

    // Fetch user from database
    try {
        $stmt = $pdo->prepare("SELECT id, email, role, first_name, last_name FROM users WHERE id = :id");
        $stmt->execute(['id' => $userId]);
        $user = $stmt->fetch();
        return $user ?: null;
    } catch (PDOException $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}
?>
