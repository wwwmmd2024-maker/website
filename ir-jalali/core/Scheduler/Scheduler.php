<?php

declare(strict_types=1);

namespace IRJalali\Core\Scheduler;

use IRJalali\Core\Database\Database;
use IRJalali\Core\Hooks\Hooks;
use IRJalali\Core\Logging\Logger;

/**
 * Cron-driven scheduler. Tasks live in `scheduled_tasks`;
 * handlers are resolved through the `scheduler.handlers` filter so that
 * plugins/modules register jobs WITHOUT touching core.
 *
 * cPanel cron (every minute):
 *   php /home/USER/ir-jalali/cron.php >/dev/null 2>&1
 */
final class Scheduler
{
    /** @var array<string, callable> */
    private array $handlers = [];

    public function __construct(
        private readonly Database $db,
        private readonly Hooks $hooks,
        private readonly Logger $logger,
    ) {
    }

    public function register(string $hook, callable $handler): void
    {
        $this->handlers[$hook] = $handler;
    }

    public function ensureTask(string $hook, string $name, string $schedule = 'hourly', array $payload = []): void
    {
        $existing = $this->db->table('scheduled_tasks')->where('hook', $hook)->first();
        if ($existing !== null) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->insert('scheduled_tasks', [
            'name' => $name,
            'hook' => $hook,
            'schedule' => $schedule,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'next_run_at' => $now,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** Run every due task. Returns number of executed tasks. */
    public function runDue(): int
    {
        $now = date('Y-m-d H:i:s');
        $tasks = $this->db->select(
            'SELECT * FROM scheduled_tasks WHERE is_active = 1 AND next_run_at <= :now ORDER BY next_run_at ASC LIMIT 20',
            ['now' => $now]
        );
        if ($tasks === []) {
            return 0;
        }

        $handlers = $this->hooks->applyFilters('scheduler.handlers', $this->handlers);
        $executed = 0;
        foreach ($tasks as $task) {
            $handler = $handlers[$task['hook']] ?? null;
            if (!is_callable($handler)) {
                $this->logger->channel('scheduler')->warning('No handler for task', ['hook' => $task['hook']]);
                $this->reschedule((int) $task['id'], (string) $task['schedule']);
                continue;
            }
            try {
                $payload = json_decode((string) ($task['payload_json'] ?? '[]'), true) ?: [];
                $handler($payload, $task);
                $executed++;
                $this->logger->channel('scheduler')->info('Task executed', ['hook' => $task['hook']]);
            } catch (\Throwable $e) {
                $this->logger->channel('scheduler')->error('Task failed', [
                    'hook' => $task['hook'],
                    'error' => $e->getMessage(),
                ]);
            }
            if (($task['schedule'] ?? '') === 'once') {
                $this->db->table('scheduled_tasks')->where('id', $task['id'])->update([
                    'is_active' => 0,
                    'last_run_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $this->reschedule((int) $task['id'], (string) $task['schedule']);
            }
        }

        return $executed;
    }

    private function reschedule(int $id, string $schedule): void
    {
        $next = match ($schedule) {
            'minutely' => '+1 minute',
            'hourly' => '+1 hour',
            'daily' => '+1 day',
            'weekly' => '+1 week',
            default => '+1 hour',
        };
        $now = date('Y-m-d H:i:s');
        $this->db->table('scheduled_tasks')->where('id', $id)->update([
            'last_run_at' => $now,
            'next_run_at' => date('Y-m-d H:i:s', strtotime($next)),
            'updated_at' => $now,
        ]);
    }
}
