<?php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Http/helpers.php';

use App\Models\User;
use App\Models\Wallet;
use App\Models\Session;
use App\Services\TransactionService;
use App\Models\Audit;
use App\Services\DatabaseConnection;

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Simple routing
if (($uri === '/' || $uri === '/index.php' || $uri === '/frontend' || $uri === '/frontend/') && $method === 'GET') {
    header('Location: /frontend/index.html');
    exit;
}

if ($uri === '/api/health' && $method === 'GET') {
    \App\Http\jsonResponse(['status' => 'ok', 'time' => date('c')]);
    exit;
}

// Registration
if ($uri === '/api/auth/register' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $fullName = \App\Http\normalizeText($input['full_name'] ?? '');
    $email = \App\Http\normalizeText($input['email'] ?? '');
    $phone = \App\Http\normalizeText($input['phone'] ?? '');
    $password = $input['password'] ?? null;
    $role = trim((string)($input['role'] ?? 'user')) ?: 'user';

    if ($fullName === '' || ($email === '' && $phone === '') || !is_string($password) || strlen($password) < 8) {
        \App\Http\jsonResponse(['error' => 'full_name, valid email or phone, and password (min 8 chars) are required'], 422);
        exit;
    }

    if ($email !== '' && !\App\Http\isValidEmail($email)) {
        \App\Http\jsonResponse(['error' => 'email is invalid'], 422);
        exit;
    }

    if ($phone !== '' && !\App\Http\isValidPhone($phone)) {
        \App\Http\jsonResponse(['error' => 'phone number is invalid'], 422);
        exit;
    }

    if (!$email && $phone) {
        $email = 'phone_' . preg_replace('/[^A-Za-z0-9]/', '', $phone) . '@francopay.local';
    }

    if (User::findByEmail($email) || ($phone !== '' && User::findByPhone($phone))) {
        \App\Http\jsonResponse(['error' => 'email or phone already registered'], 409);
        exit;
    }

    try {
        $userId = User::create($fullName, $email, $password, $phone !== '' ? $phone : null, $role);
        $walletNumber = 'FP' . str_pad((string)$userId, 8, '0', STR_PAD_LEFT);
        Wallet::create($userId, $walletNumber, 0.00);
        \App\Http\jsonResponse(['message' => 'account created', 'wallet_number' => $walletNumber], 201);
    } catch (Exception $e) {
        \App\Http\jsonResponse(['error' => 'unable to create account'], 500);
    }
    exit;
}

// Login
if ($uri === '/api/auth/login' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $identifier = \App\Http\normalizeText($input['email'] ?? $input['phone'] ?? '');
    $password = $input['password'] ?? null;

    if ($identifier === '' || !is_string($password) || strlen($password) < 8) {
        \App\Http\jsonResponse(['error' => 'valid email or phone and password (min 8 chars) are required'], 422);
        exit;
    }

    $user = User::findByLoginIdentifier($identifier);
    if (!$user || !password_verify($password, $user['password_hash'])) {
        \App\Http\jsonResponse(['error' => 'invalid credentials'], 401);
        exit;
    }
    // Create a session token and persist
    $token = bin2hex(random_bytes(16));
    try {
        Session::create((int)$user['id'], $token, null);
    } catch (Exception $e) {
        \App\Http\jsonResponse(['error' => 'unable to create session'], 500);
        exit;
    }

    \App\Http\jsonResponse(['message' => 'authenticated', 'token' => $token, 'user' => ['full_name' => $user['full_name'], 'role' => $user['role'] ?? 'user']]);
    exit;
}

if ($uri === '/api/auth/logout' && $method === 'POST') {
    $authUser = \App\Http\requireAuth();
    $token = \App\Http\getBearerToken();
    if (!$token) {
        \App\Http\jsonResponse(['error' => 'Unauthorized'], 401);
        exit;
    }
    Session::deleteByToken($token);
    \App\Http\jsonResponse(['status' => 'logged_out', 'message' => 'session ended']);
    exit;
}

// Wallet - require auth
if ($uri === '/api/wallet' && $method === 'GET') {
    $authUser = \App\Http\requireAuth();
    $wallet = Wallet::findByUserId((int)$authUser['id']);
    if (!$wallet) {
        \App\Http\jsonResponse(['error' => 'wallet not found'], 404);
        exit;
    }
    \App\Http\jsonResponse([
        'wallet_number' => $wallet['wallet_number'],
        'balance' => (float)$wallet['balance'],
        'currency' => $wallet['currency'],
        'full_name' => $authUser['full_name'],
        'bonus_eligible' => (int)($wallet['bonus_claimed'] ?? 0) === 0,
        'server_time' => date('c')
    ]);
    exit;
}

if ($uri === '/api/wallet/resolve' && $method === 'GET') {
    $authUser = \App\Http\requireAuth();
    $walletNumber = trim((string)($_GET['wallet_number'] ?? ''));
    if (!$walletNumber) {
        \App\Http\jsonResponse(['error' => 'wallet_number is required'], 422);
        exit;
    }

    $wallet = Wallet::findByWalletNumber($walletNumber);
    if (!$wallet) {
        \App\Http\jsonResponse(['error' => 'recipient wallet not found'], 404);
        exit;
    }

    $recipient = \App\Models\User::findById((int)$wallet['user_id']);
    \App\Http\jsonResponse([
        'wallet_number' => $wallet['wallet_number'],
        'full_name' => $recipient['full_name'] ?? 'Unknown User',
        'balance' => (float)$wallet['balance'],
        'currency' => $wallet['currency']
    ]);
    exit;
}

if ($uri === '/api/wallet/claim-bonus' && $method === 'POST') {
    $authUser = \App\Http\requireAuth();
    $wallet = Wallet::claimWelcomeBonus((int)$authUser['id']);
    if (!$wallet) {
        \App\Http\jsonResponse(['error' => 'wallet not found'], 404);
        exit;
    }
    \App\Http\jsonResponse([
        'status' => 'claimed',
        'wallet_number' => $wallet['wallet_number'],
        'balance' => (float)$wallet['balance'],
        'currency' => $wallet['currency'],
        'bonus_eligible' => false,
        'bonus_amount' => 100000.00
    ]);
    exit;
}

// Create Transaction
if ($uri === '/api/transactions' && $method === 'POST') {
    $authUser = \App\Http\requireAuth();
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $recipient = \App\Http\normalizeText((string)($input['recipient'] ?? ''));
    $amount = isset($input['amount']) ? (float)$input['amount'] : null;
    $description = \App\Http\normalizeText((string)($input['description'] ?? ''));

    $idempotencyKey = $_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? ($_SERVER['HTTP_IDEMPOTENCYKEY'] ?? null);

    if ($recipient === '' || !is_numeric($amount) || (float)$amount <= 0) {
        \App\Http\jsonResponse(['error' => 'valid recipient and positive amount are required'], 422);
        exit;
    }

    if (!\App\Http\isValidWalletNumber($recipient)) {
        \App\Http\jsonResponse(['error' => 'recipient wallet number is invalid'], 422);
        exit;
    }

    if ($description !== '' && strlen($description) > 160) {
        \App\Http\jsonResponse(['error' => 'description is too long'], 422);
        exit;
    }

    $result = TransactionService::createTransaction((int)$authUser['id'], $recipient, $amount, $idempotencyKey, $description === '' ? null : $description);
    if ($result['status'] === 'SUCCESS') {
        \App\Http\jsonResponse(['status' => 'SUCCESS', 'transaction' => $result['transaction']], 201);
    } else {
        \App\Http\jsonResponse(['status' => 'FAILED', 'reason' => $result['reason'] ?? 'ERROR', 'details' => $result['message'] ?? null], 400);
    }
    exit;
}

// Transaction history
if ($uri === '/api/transactions' && $method === 'GET') {
    $authUser = \App\Http\requireAuth();
    $pdo = App\Services\DatabaseConnection::get();
    $wallet = Wallet::findByUserId((int)$authUser['id']);
    if (!$wallet) {\App\Http\jsonResponse(['transactions' => []]); exit;}
    $stmt = $pdo->prepare('SELECT * FROM transactions WHERE sender_wallet_id = ? OR receiver_wallet_id = ? ORDER BY created_at DESC');
    $stmt->execute([(int)$wallet['id'], (int)$wallet['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    \App\Http\jsonResponse(['transactions' => $rows]);
    exit;
}

if (preg_match('#^/api/transactions/([^/]+)$#', $uri, $matches) && $method === 'GET') {
    $authUser = \App\Http\requireAuth();
    $reference = rawurldecode($matches[1]);
    $transaction = \App\Models\TransactionModel::findByReference($reference);
    if (!$transaction) {
        \App\Http\jsonResponse(['error' => 'transaction not found'], 404);
        exit;
    }

    $wallet = Wallet::findByUserId((int)$authUser['id']);
    if (!$wallet) {
        \App\Http\jsonResponse(['error' => 'wallet not found'], 404);
        exit;
    }

    if ((int)$transaction['sender_wallet_id'] !== (int)$wallet['id'] && (int)$transaction['receiver_wallet_id'] !== (int)$wallet['id']) {
        \App\Http\jsonResponse(['error' => 'Forbidden'], 403);
        exit;
    }

    \App\Http\jsonResponse(['transaction' => $transaction]);
    exit;
}

if ($uri === '/api/admin/audit' && $method === 'GET') {
    $authUser = \App\Http\requireAuth();
    if (($authUser['role'] ?? 'user') !== 'admin') {
        \App\Http\jsonResponse(['error' => 'Forbidden'], 403);
        exit;
    }

    // Server-side filtering and pagination
    $page = max(1, (int)($_GET['page'] ?? 1));
    $pageSize = (int)($_GET['page_size'] ?? 25);
    if ($pageSize < 1) $pageSize = 1;
    if ($pageSize > 100) $pageSize = 100;

    $q = trim((string)($_GET['q'] ?? ''));
    $filterAction = trim((string)($_GET['action'] ?? ''));
    $filterUser = trim((string)($_GET['user_id'] ?? ''));
    $filterEntity = trim((string)($_GET['entity_type'] ?? ''));
    $from = trim((string)($_GET['from'] ?? ''));
    $to = trim((string)($_GET['to'] ?? ''));

    $pdo = App\Services\DatabaseConnection::get();
    $where = [];
    $params = [];

    if ($q !== '') {
        $like = '%' . str_replace('%', '\\%', $q) . '%';
        $where[] = '(action LIKE ? OR entity_type LIKE ? OR entity_id LIKE ? OR metadata LIKE ? OR ip_address LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
    }

    if ($filterAction !== '') {
        $where[] = 'action = ?';
        $params[] = $filterAction;
    }

    if ($filterEntity !== '') {
        $where[] = 'entity_type = ?';
        $params[] = $filterEntity;
    }

    if ($filterUser !== '') {
        // allow numeric ids only for user filter
        if (is_numeric($filterUser)) {
            $where[] = 'user_id = ?';
            $params[] = (int)$filterUser;
        }
    }

    // Date range filtering (accepts YYYY-MM-DD or full datetime)
    if ($from !== '') {
        // normalize date-only to start of day
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = $from . ' 00:00:00';
        }
        $where[] = 'created_at >= ?';
        $params[] = $from;
    }

    if ($to !== '') {
        // normalize date-only to end of day
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = $to . ' 23:59:59';
        }
        $where[] = 'created_at <= ?';
        $params[] = $to;
    }

    $whereSql = '';
    if (count($where) > 0) {
        $whereSql = 'WHERE ' . implode(' AND ', $where);
    }

    // total count for pagination
    $countSql = 'SELECT COUNT(*) as c FROM audit_logs ' . $whereSql;
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['c'];

    $totalPages = (int)max(1, ceil($total / $pageSize));
    if ($page > $totalPages) $page = $totalPages;
    $offset = ($page - 1) * $pageSize;

    $sql = 'SELECT * FROM audit_logs ' . $whereSql . ' ORDER BY created_at DESC LIMIT ? OFFSET ?';
    $stmt = $pdo->prepare($sql);
    $execParams = array_merge($params, [$pageSize, $offset]);
    $stmt->execute($execParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    \App\Http\jsonResponse([
        'status' => 'ok',
        'items' => $rows,
        'pagination' => [
            'page' => $page,
            'page_size' => $pageSize,
            'total' => $total,
            'total_pages' => $totalPages
        ]
    ]);
    exit;
}

http_response_code(404);
\App\Http\jsonResponse(['error' => 'Not Found'], 404);
