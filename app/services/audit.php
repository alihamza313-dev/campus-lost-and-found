<?php
declare(strict_types=1);

/**
 * Appends one row to audit_logs. Call it INSIDE the same transaction as the change it records (BR-15),
 * so the change and its log row either both happen or both roll back.
 */
function audit_log(?int $actorId, string $action, string $targetType, int $targetId, ?string $details = null): void
{
    $stmt = db()->prepare(
        'INSERT INTO audit_logs (actor_id, action, target_type, target_id, details, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$actorId, $action, $targetType, $targetId, $details, client_ip()]);
}