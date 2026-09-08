<?php
namespace App\Models;

use App\Services\DatabaseConnection;

class TransactionModel
{
    public static function findByIdempotency(string $idempotencyKey, int $senderWalletId): ?array
    {
        if (!$idempotencyKey) return null;
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare('SELECT * FROM transactions WHERE idempotency_key = ? AND sender_wallet_id = ? LIMIT 1');
        $stmt->execute([$idempotencyKey, $senderWalletId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function insert(array $data): int
    {
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare('INSERT INTO transactions (transaction_reference, idempotency_key, sender_wallet_id, receiver_wallet_id, amount, currency, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $data['transaction_reference'],
            $data['idempotency_key'] ?? null,
            $data['sender_wallet_id'],
            $data['receiver_wallet_id'],
            $data['amount'],
            $data['currency'] ?? 'GHS',
            $data['status'],
            $data['description'] ?? null
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function findById(int $id): ?array
    {
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public static function findByReference(string $reference): ?array
    {
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare('SELECT * FROM transactions WHERE transaction_reference = ? LIMIT 1');
        $stmt->execute([$reference]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }
}
