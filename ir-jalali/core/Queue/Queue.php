<?php

declare(strict_types=1);

namespace IRJalali\Core\Queue;

use IRJalali\Core\Database\Database;

/**
 * Minimal durable queue backed by `scheduled_tasks` (schedule = once).
 * Workers = the same cron entrypoint (cron.php). No Redis required.
 */
final class Queue
{
    public function __construct(private readonly Database $db)
    {
    }

    public function push(string $hook, array $payload = [], int $delaySeconds = 0): int
    {
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('scheduled_tasks', [
            'name' => 'job:' . $hook,
            'hook' => $hook,
            'schedule' => 'once',
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'next_run_at' => date('Y-m-d H:i:s', time() + max(0, $delaySeconds)),
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function pendingCount(): int
    {
        return (int) $this->db->value(
            "SELECT COUNT(*) FROM scheduled_tasks WHERE is_active = 1 AND schedule = 'once'"
        );
    }
}
