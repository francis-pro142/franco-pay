<?php
namespace App\Http;

function normalizeText(?string $value): string
{
    return trim(preg_replace('/\s+/', ' ', (string)($value ?? '')));
}

function hashSensitiveValue(?string $value): ?string
{
    $string = (string)($value ?? '');
    if ($string === '') {
        return null;
    }
    $secret = getenv('APP_KEY') ?: 'franco-pay-demo-key';
    return hash('sha256', $string . ':' . $secret);
}

function encryptSensitiveValue(?string $value): ?string
{
    $string = (string)($value ?? '');
    if ($string === '') {
        return null;
    }
    $key = substr(hash('sha256', getenv('APP_KEY') ?: 'franco-pay-demo-key'), 0, 32);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($string, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        return null;
    }
    return base64_encode($iv . $encrypted);
}

function isValidEmail(?string $email): bool
{
    return is_string($email) && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone(?string $phone): bool
{
    if (!is_string($phone)) {
        return false;
    }
    $phone = preg_replace('/\s+/', '', trim($phone));
    return $phone !== '' && preg_match('/^\+?[0-9\-()]{7,20}$/', $phone) === 1;
}

function isValidWalletNumber(?string $walletNumber): bool
{
    return is_string($walletNumber) && preg_match('/^FP\d{8}$/', trim($walletNumber)) === 1;
}

function jsonResponse($data, int $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
}

function getBearerToken(): ?string
{
    $headers = null;
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('getallheaders')) {
        $all = getallheaders();
        if (!empty($all['Authorization'])) $headers = trim($all['Authorization']);
        if (!empty($all['authorization'])) $headers = trim($all['authorization']);
    }

    if (!$headers) return null;
    if (stripos($headers, 'Bearer ') === 0) {
        return substr($headers, 7);
    }
    return null;
}

function requireAuth()
{
    $token = getBearerToken();
    if (!$token) {
        jsonResponse(['error' => 'Unauthorized'], 401);
        exit;
    }
    $session = \App\Models\Session::findByToken($token);
    if (!$session) {
        jsonResponse(['error' => 'Unauthorized'], 401);
        exit;
    }
    $user = \App\Models\User::findById((int)$session['user_id']);
    if (!$user) {
        jsonResponse(['error' => 'Unauthorized'], 401);
        exit;
    }
    return $user;
}
