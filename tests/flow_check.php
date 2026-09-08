<?php

function request(string $method, string $url, array $data = [], array $headers = []): array {
    $payload = null;
    if ($data) {
        $payload = json_encode($data);
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $payload,
            'ignore_errors' => true,
            'timeout' => 20,
        ],
    ]);

    $raw = @file_get_contents($url, false, $context);
    if ($raw === false) {
        throw new RuntimeException("Request failed: $url");
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : ['raw' => $raw];
}

$base = 'http://localhost:8080';

$health = request('GET', $base . '/api/health');
if (($health['status'] ?? null) !== 'ok') {
    throw new RuntimeException('Health check failed: ' . json_encode($health));
}

echo "Health OK\n";

$email = 'flow_' . uniqid() . '@example.test';
$phone = '+1555' . rand(1000000, 9999999);
$register = request('POST', $base . '/api/auth/register', [
    'full_name' => 'Flow Tester',
    'email' => $email,
    'phone' => $phone,
    'password' => 'secret123',
    'role' => 'admin',
], ['Content-Type: application/json']);
if (($register['message'] ?? null) !== 'account created' && ($register['wallet_number'] ?? null) === null) {
    throw new RuntimeException('Register failed: ' . json_encode($register));
}

echo "Register OK\n";

$login = request('POST', $base . '/api/auth/login', [
    'email' => $email,
    'password' => 'secret123',
], ['Content-Type: application/json']);
if (empty($login['token'])) {
    throw new RuntimeException('Login failed: ' . json_encode($login));
}

$token = $login['token'];
echo "Login OK\n";

$logout = request('POST', $base . '/api/auth/logout', [], ['Authorization: Bearer ' . $token]);
if (($logout['status'] ?? null) !== 'logged_out') {
    throw new RuntimeException('Logout failed: ' . json_encode($logout));
}

echo "Logout OK\n";

$loginAgain = request('POST', $base . '/api/auth/login', [
    'phone' => $phone,
    'password' => 'secret123',
], ['Content-Type: application/json']);
if (empty($loginAgain['token'])) {
    throw new RuntimeException('Phone login failed: ' . json_encode($loginAgain));
}
$token = $loginAgain['token'];

echo "Phone login OK\n";

$wallet = request('GET', $base . '/api/wallet', [], ['Authorization: Bearer ' . $token]);
if (empty($wallet['wallet_number'])) {
    throw new RuntimeException('Wallet fetch failed: ' . json_encode($wallet));
}
if ((float)($wallet['balance'] ?? 0) !== 0.0) {
    throw new RuntimeException('New account should start at zero balance before bonus acceptance: ' . json_encode($wallet));
}
if (empty($wallet['bonus_eligible'])) {
    throw new RuntimeException('Welcome bonus should be eligible on first login: ' . json_encode($wallet));
}

echo "Wallet OK\n";

$bonus = request('POST', $base . '/api/wallet/claim-bonus', [], ['Authorization: Bearer ' . $token]);
if (($bonus['status'] ?? null) !== 'claimed' || ((float)($bonus['balance'] ?? 0)) < 100000) {
    throw new RuntimeException('Welcome bonus claim failed: ' . json_encode($bonus));
}

echo "Bonus claim OK\n";

$recipientEmail = 'recipient_' . uniqid() . '@example.test';
$recipientReg = request('POST', $base . '/api/auth/register', [
    'full_name' => 'Recipient Flow',
    'email' => $recipientEmail,
    'password' => 'secret123',
], ['Content-Type: application/json']);
if (empty($recipientReg['wallet_number'])) {
    throw new RuntimeException('Recipient registration failed: ' . json_encode($recipientReg));
}
$recipientLogin = request('POST', $base . '/api/auth/login', [
    'email' => $recipientEmail,
    'password' => 'secret123',
], ['Content-Type: application/json']);
if (empty($recipientLogin['token'])) {
    throw new RuntimeException('Recipient login failed: ' . json_encode($recipientLogin));
}
$recipientWallet = request('GET', $base . '/api/wallet', [], ['Authorization: Bearer ' . $recipientLogin['token']]);
$recipientWalletNumber = $recipientWallet['wallet_number'];

$recipientLookup = request('GET', $base . '/api/wallet/resolve?wallet_number=' . urlencode($recipientWalletNumber), [], ['Authorization: Bearer ' . $token]);
if (empty($recipientLookup['full_name'])) {
    throw new RuntimeException('Recipient lookup failed: ' . json_encode($recipientLookup));
}

echo "Recipient lookup OK\n";

$selfTransfer = request('POST', $base . '/api/transactions', [
    'recipient' => $wallet['wallet_number'],
    'amount' => 25.00,
    'description' => 'Security test: self transfer',
], ['Content-Type: application/json', 'Authorization: Bearer ' . $token, 'Idempotency-Key: self-transfer-' . uniqid()]);
if (($selfTransfer['status'] ?? null) === 'SUCCESS') {
    throw new RuntimeException('Self transfer should be blocked: ' . json_encode($selfTransfer));
}

echo "Security check OK\n";

$idempotency = 'demo-key-' . uniqid();
$transfer = request('POST', $base . '/api/transactions', [
    'recipient' => $recipientWalletNumber,
    'amount' => 100.00,
    'description' => 'Prototype transfer',
], ['Content-Type: application/json', 'Authorization: Bearer ' . $token, 'Idempotency-Key: ' . $idempotency]);
if (($transfer['status'] ?? null) !== 'SUCCESS') {
    throw new RuntimeException('Transfer failed: ' . json_encode($transfer));
}

echo "Transaction OK\n";

$repeat = request('POST', $base . '/api/transactions', [
    'recipient' => $recipientWalletNumber,
    'amount' => 100.00,
    'description' => 'Prototype transfer',
], ['Content-Type: application/json', 'Authorization: Bearer ' . $token, 'Idempotency-Key: ' . $idempotency]);
if (($repeat['status'] ?? null) !== 'SUCCESS') {
    throw new RuntimeException('Idempotency duplicate failed: ' . json_encode($repeat));
}

echo "Duplicate request OK\n";

$detail = request('GET', $base . '/api/transactions/' . urlencode($transfer['transaction']['transaction_reference']), [], ['Authorization: Bearer ' . $token]);
if (empty($detail['transaction']) || ($detail['transaction']['transaction_reference'] ?? null) !== $transfer['transaction']['transaction_reference']) {
    throw new RuntimeException('Transaction detail lookup failed: ' . json_encode($detail));
}

echo "Transaction detail OK\n";

$adminAudit = request('GET', $base . '/api/admin/audit', [], ['Authorization: Bearer ' . $token]);
if (($adminAudit['status'] ?? null) !== 'ok' || empty($adminAudit['items'])) {
    throw new RuntimeException('Admin audit failed: ' . json_encode($adminAudit));
}

echo "Admin audit OK\n";

echo "All checks passed.\n";
