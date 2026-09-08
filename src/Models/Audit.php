<?php
namespace App\Models;

use App\Services\DatabaseConnection;

class Audit
{
    public static function log(?int $userId, string $action, ?string $entityType = null, $entityId = null, ?string $ip = null, $metadata = null): int
    {
        $pdo = DatabaseConnection::get();
        $stmt = $pdo->prepare('INSERT INTO audit_logs (user_id, action, entity_type, entity_id, ip_address, metadata) VALUES (?, ?, ?, ?, ?, ?)');
        $metaText = null;
        if ($metadata !== null) {
            $metaText = is_string($metadata) ? $metadata : json_encode($metadata);
            $metaText = \App\Http\hashSensitiveValue($metaText) ?: $metaText;
        }
        $stmt->execute([$userId, $action, $entityType, $entityId, $ip, $metaText]);
        return (int)$pdo->lastInsertId();
    }
}
