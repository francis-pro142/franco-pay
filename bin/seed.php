#!/usr/bin/env php
<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Services\DatabaseConnection;

$pdo = DatabaseConnection::get();

function insertUser(PDO $pdo, $fullName, $email, $password, $role = 'user')
{
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$fullName, $email, $hash, $role]);
    return (int)$pdo->lastInsertId();
}

function createWallet(PDO $pdo, $userId, $walletNumber, $balance = 0.00)
{
    $stmt = $pdo->prepare('INSERT INTO wallets (user_id, wallet_number, balance, bonus_claimed) VALUES (?, ?, ?, 0)');
    $stmt->execute([$userId, $walletNumber, $balance]);
}

try {
    $pdo->beginTransaction();

    // Create admin user
    $adminPassword = 'adminpass123';
    $adminId = insertUser($pdo, 'System Admin', 'admin@example.test', $adminPassword, 'admin');
    $adminWallet = 'FP' . str_pad((string)$adminId, 8, '0', STR_PAD_LEFT);
    createWallet($pdo, $adminId, $adminWallet, 1000.00);

    // Demo user: Francis
    $id1 = insertUser($pdo, 'Francis Quayson', 'francis@example.test', 'password123');
    $wallet1 = 'FP' . str_pad((string)$id1, 8, '0', STR_PAD_LEFT);
    createWallet($pdo, $id1, $wallet1, 1000.00);

    // Demo user: John
    $id2 = insertUser($pdo, 'John Doe', 'john@example.test', 'password123');
    $wallet2 = 'FP' . str_pad((string)$id2, 8, '0', STR_PAD_LEFT);
    createWallet($pdo, $id2, $wallet2, 200.00);

    $pdo->commit();
    echo "Seeded users and wallets.\n";
    echo "Admin account: admin@example.test / {$adminPassword} (wallet: {$adminWallet})\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "Seeding failed: " . $e->getMessage() . "\n";
}
