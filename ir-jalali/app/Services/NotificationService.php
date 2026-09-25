<?php

declare(strict_types=1);

namespace IRJalali\App\Services;

use IRJalali\Core\Database\Database;
use IRJalali\Core\Events\Dispatcher;
use IRJalali\Core\Kernel\Application;

/**
 * Notification Center (Part 3 §16). Persists rows into the core
 * `notifications` table and broadcasts them over the event bus so plugins
 * (IR-SMTP, future push providers) can fan them out.
 *
 * Types used across the platform:
 *   user.new | form.new | order.new | booking.new | security.alert
 *   plugin.update | theme.update | core.update | system.error
 */
final class NotificationService
{
    public function __construct(
        private readonly Database $db,
        private readonly Dispatcher $events,
    ) {
    }

    /** @param array<string, mixed> $meta */
    public function push(string $type, string $title, string $body = '', string $url = '', ?int $userId = null, array $meta = []): int
    {
        $title = mb_substr($title, 0, 190);
        $body = mb_substr($body, 0, 4000);
        $url = mb_substr($url, 0, 500);

        $id = (int) $this->db->insert('notifications', [
            'uuid' => $this->uuid(),
            'user_id' => $userId,
            'type' => mb_substr($type, 0, 60),
            'title' => $title,
            'body' => $body,
            'url' => $url !== '' ? $url : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->events->dispatch(new class($type, $title, $body, $url, $userId, $meta) {
            public function __construct(
                public readonly string $type,
                public readonly string $title,
                public readonly string $body,
                public readonly string $url,
                public readonly ?int $userId,
                public readonly array $meta,
            ) {
            }
        });

        return $id;
    }

    /** Convenience: notify every admin user. */
    public function pushToAdmins(string $type, string $title, string $body = '', string $url = '', array $meta = []): void
    {
        $this->push($type, $title, $body, $url, null, $meta);
    }

    /** @return list<array<string, mixed>> */
    public function recent(int $limit = 50, int $offset = 0): array
    {
        try {
            return $this->db->select(
                'SELECT * FROM notifications ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)) . ' OFFSET ' . max(0, $offset)
            );
        } catch (\Throwable) {
            return [];
        }
    }

    public function count(bool $onlyUnread = false): int
    {
        try {
            $sql = 'SELECT COUNT(*) AS c FROM notifications' . ($onlyUnread ? ' WHERE read_at IS NULL' : '');
            $row = $this->db->first($sql);

            return (int) ($row['c'] ?? 0);
        } catch (\Throwable) {
            return 0;
        }
    }

    public function markRead(int $id): void
    {
        $this->db->table('notifications')->where('id', $id)->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    public function markAllRead(): int
    {
        return $this->db->update('notifications', ['read_at' => date('Y-m-d H:i:s')], 'read_at IS NULL');
    }

    public function delete(int $id): void
    {
        $this->db->table('notifications')->where('id', $id)->delete();
    }

    public function clearRead(): int
    {
        return $this->db->delete('notifications', 'read_at IS NOT NULL');
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
