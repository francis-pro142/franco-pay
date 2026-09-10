<?php
namespace App\Models;

use App\Services\DatabaseConnection;
use PDO;

class Wallet
{
    public static function ensureBonusColumn(): void
    {
        $pdo = DatabaseConnection::get();
        $hasBonus = false;
        try {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        } catch (\Throwable $e) {
            $driver = 'sqlite';
        }

        if ($driver === 'sqlite') {
            $cols = $pdo->query("PRAGMA table_info(wallets)")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($cols as $col) {
                if (($col['name'] ?? '') === 'bonus_claimed') {
                    $hasBonus = true;
                    break;
                }
            }
            if (!$hasBonus) {
                $pdo->exec('ALTER TABLE wallets ADD COLUMN bonus_claimed INTEGER NOT NULL DEFAULT 0');
            }
        } else {
            // Assume MySQL or compatible
            try {
                $stmt = $pdo->prepare("SHOW COLUMNS FROM wallets LIKE 'bonus_claimed'");
                $stmt->execute();
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) $hasBonus = true;
            } catch (\Throwable $e) {
                $hasBonus = false;
            }

            if (!$hasBonus) {
                // Use a MySQL-friendly column type
                $pdo->exec('ALTER TABLE wallets ADD COLUMN bonus_claimed TINYINT(1) NOT NULL DEFAULT 0');
            }
        }
    }

    public static function create(int $userId, string $walletNumber, float $balance = 0.0): int
    {
        self::ensureBonusColumn();
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare('INSERT INTO wallets (user_id, wallet_number, balance, bonus_claimed) VALUES (?, ?, ?, 0)');
        $stmt->execute([$userId, $walletNumber, $balance]);
        return (int)$pdo->lastInsertId();
    }

    public static function findByUserId(int $userId): ?array
    {
        self::ensureBonusColumn();
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare('SELECT * FROM wallets WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function findByWalletNumber(string $walletNumber): ?array
    {
        self::ensureBonusColumn();
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare('SELECT * FROM wallets WHERE wallet_number = ? LIMIT 1');
        $stmt->execute([$walletNumber]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public static function claimWelcomeBonus(int $userId): ?array
    {
        self::ensureBonusColumn();
        $pdo = DatabaseConnection::get();
        $wallet = self::findByUserId($userId);
        if (!$wallet) {
            return null;
        }

        if ((int)($wallet['bonus_claimed'] ?? 0) === 1) {
            return $wallet;
        }

        $newBalance = (float)$wallet['balance'] + 100000.00;
        // Use DB-specific function for current timestamp
        $driver = 'sqlite';
        try { $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME); } catch (\Throwable $e) {}
        if ($driver === 'sqlite') {
            $stmt = $pdo->prepare('UPDATE wallets SET balance = ?, bonus_claimed = 1, updated_at = datetime("now") WHERE user_id = ?');
        } else {
            $stmt = $pdo->prepare('UPDATE wallets SET balance = ?, bonus_claimed = 1, updated_at = NOW() WHERE user_id = ?');
        }
        $stmt->execute([$newBalance, $userId]);

        $wallet['balance'] = $newBalance;
        $wallet['bonus_claimed'] = 1;
        return $wallet;
    }
}
