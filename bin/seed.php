#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Services\DatabaseConnection;

$pdo = DatabaseConnection::get();

function insertUser(PDO $pdo, $fullName, $email, $password)
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$fullName, $email, $hash]);
    return $pdo->lastInsertId();
}

function createWallet(PDO $pdo, $userId, $walletNumber, $balance = 0.00)
{
    $stmt = $pdo->prepare('INSERT INTO wallets (user_id, wallet_number, balance) VALUES (?, ?, ?)');
    $stmt->execute([$userId, $walletNumber, $balance]);
}

try {
    $pdo->beginTransaction();

    $id1 = insertUser($pdo, 'Francis Quayson', 'francis@example.test', 'password123');
    createWallet($pdo, $id1, 'WLT-000001', 1000.00);

    $id2 = insertUser($pdo, 'John Doe', 'john@example.test', 'password123');
    createWallet($pdo, $id2, 'WLT-000002', 200.00);

    $pdo->commit();
    echo "Seeded users and wallets.\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Seeding failed: " . $e->getMessage() . "\n";
}
