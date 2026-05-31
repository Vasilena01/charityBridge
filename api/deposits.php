<?php
require_once __DIR__ . '/../backend/includes/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: http://localhost:5500');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-User-Id');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

const MIN_DEPOSIT = 1.00;
const MAX_DEPOSIT = 10000.00;

$response = ['success' => false];
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            $response = listMyDeposits($pdo);
            break;

        case 'POST':
            $response = createDeposit($pdo);
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

function listMyDeposits($pdo) {
    $user = requireAuthUser($pdo);
    $stmt = $pdo->prepare("
        SELECT id, amount, balance_before, balance_after,
               payment_method, card_last4, card_holder, status, created_at
        FROM deposits
        WHERE user_id = :uid
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute(['uid' => $user['id']]);
    return ['success' => true, 'deposits' => $stmt->fetchAll()];
}

function createDeposit($pdo) {
    $user = requireAuthUser($pdo);

    if (!in_array($user['role'], ['volunteer', 'company'], true)) {
        http_response_code(403);
        throw new Exception('Only volunteers and companies can deposit funds');
    }

    $input = readJsonInput();

    $amount = $input['amount'] ?? null;
    if (!is_numeric($amount)) {
        http_response_code(400);
        throw new Exception('Amount is required and must be numeric');
    }
    $amount = round((float)$amount, 2);
    if ($amount < MIN_DEPOSIT) {
        http_response_code(400);
        throw new Exception('Minimum deposit is ' . number_format(MIN_DEPOSIT, 2));
    }
    if ($amount > MAX_DEPOSIT) {
        http_response_code(400);
        throw new Exception('Maximum deposit is ' . number_format(MAX_DEPOSIT, 2));
    }

    $cardNumberRaw = preg_replace('/\s+/', '', (string)($input['card_number'] ?? ''));
    if (!preg_match('/^\d{13,19}$/', $cardNumberRaw)) {
        http_response_code(400);
        throw new Exception('Card number must be 13–19 digits');
    }
    $cardLast4 = substr($cardNumberRaw, -4);

    $cardHolder = trim((string)($input['card_holder'] ?? ''));
    if ($cardHolder === '' || strlen($cardHolder) > 100) {
        http_response_code(400);
        throw new Exception('Card holder name is required (max 100 chars)');
    }

    $expiry = trim((string)($input['expiry'] ?? ''));
    if (!preg_match('/^(0[1-9]|1[0-2])\s*\/\s*\d{2}$/', $expiry)) {
        http_response_code(400);
        throw new Exception('Expiry must be in MM/YY format');
    }

    $cvv = trim((string)($input['cvv'] ?? ''));
    if (!preg_match('/^\d{3,4}$/', $cvv)) {
        http_response_code(400);
        throw new Exception('CVV must be 3 or 4 digits');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT virtual_balance FROM users WHERE id = :id");
        $stmt->execute(['id' => $user['id']]);
        $balanceBefore = (float)$stmt->fetchColumn();
        $balanceAfter = round($balanceBefore + $amount, 2);

        $pdo->prepare("UPDATE users SET virtual_balance = :b WHERE id = :id")
            ->execute(['b' => $balanceAfter, 'id' => $user['id']]);

        $pdo->prepare("
            INSERT INTO deposits
                (user_id, amount, balance_before, balance_after,
                 payment_method, card_last4, card_holder, status)
            VALUES
                (:uid, :amount, :before, :after,
                 'mock_card', :last4, :holder, 'completed')
        ")->execute([
            'uid' => $user['id'],
            'amount' => number_format($amount, 2, '.', ''),
            'before' => number_format($balanceBefore, 2, '.', ''),
            'after' => number_format($balanceAfter, 2, '.', ''),
            'last4' => $cardLast4,
            'holder' => $cardHolder,
        ]);

        $depositId = (int)$pdo->lastInsertId();
        $pdo->commit();

        return [
            'success' => true,
            'deposit_id' => $depositId,
            'amount' => $amount,
            'new_balance' => $balanceAfter,
            'card_last4' => $cardLast4,
            'message' => 'Deposit successful',
        ];
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
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
        error_log("deposits requireAuthUser: " . $e->getMessage());
        http_response_code(500);
        throw new Exception('Server error');
    }
}

function readJsonInput() {
    $raw = file_get_contents('php://input');
    $input = $raw ? json_decode($raw, true) : null;
    return $input ?: $_POST;
}
