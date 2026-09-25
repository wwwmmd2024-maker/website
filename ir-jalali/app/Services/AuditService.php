<?php

declare(strict_types=1);

namespace IRJalali\App\Services;

use IRJalali\Core\Database\Database;
use IRJalali\Core\Logging\Logger;

/**
 * Dual-writes admin/security events to DB tables AND file logs.
 */
final class AuditService
{
    public function __construct(
        private readonly Database $db,
        private readonly Logger $logger,
    ) {
    }

    public function audit(?int $userId, string $action, ?string $entityType = null, ?int $entityId = null, array $before = [], array $after = []): void
    {
        $this->db->insert('audit_logs', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_json' => $before === [] ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
            'after_json' => $after === [] ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->logger->channel('admin')->info($action, ['user' => $userId, 'entity' => $entityType]);
    }

    public function security(string $event, ?int $userId = null, array $details = []): void
    {
        $this->db->insert('security_logs', [
            'event' => $event,
            'user_id' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'cli', 0, 250),
            'details_json' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->logger->channel('security')->warning($event, ['user' => $userId] + $details);
    }

    public function loginSuccess(int $userId): void
    {
        $this->security('login.success', $userId);
    }

    public function loginFailed(string $login): void
    {
        $this->security('login.failed', null, ['login' => $login]);
    }
}
