<?php
namespace App\Models;

use App\Services\DatabaseConnection;

class Session
{
    public static function create(int $userId, string $token, ?string $expiresAt = null): int
    {
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare('INSERT INTO sessions (user_id, token, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $token, $expiresAt]);
        return (int)$pdo->lastInsertId();
    }

    public static function findByToken(string $token): ?array
    {
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare('SELECT * FROM sessions WHERE token = ? LIMIT 1');
        $stmt->execute([$token]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function deleteByToken(string $token): bool
    {
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare('DELETE FROM sessions WHERE token = ?');
        return $stmt->execute([$token]);
    }
}
