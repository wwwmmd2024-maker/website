<?php

declare(strict_types=1);

namespace IRJalali\Core\Builder;

use IRJalali\Core\Database\Database;

/**
 * Builder snapshots on top of the core `revisions` table:
 * manual saves, autosaves (pruned), restore, recovery detection.
 */
final class RevisionManager
{
    public const MAX_AUTOSAVES = 5;
    public const MAX_REVISIONS = 30;

    public function __construct(private readonly Database $db)
    {
    }

    public function snapshot(string $entityType, int $entityId, ?int $userId, string $title, string $contentJson, bool $autosave = false): int
    {
        $id = (int) $this->db->insert('revisions', [
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'title' => mb_substr($title, 0, 240),
            'snapshot' => $contentJson,
            'is_autosave' => $autosave ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->prune($entityType, $entityId, $autosave);

        return $id;
    }

    /** @return list<array<string, mixed>> newest first */
    public function history(string $entityType, int $entityId, int $limit = 30): array
    {
        return $this->db->select(
            'SELECT r.*, u.display_name AS author_name FROM revisions r
             LEFT JOIN users u ON u.id = r.user_id
             WHERE r.entity_type = :t AND r.entity_id = :id
             ORDER BY r.id DESC LIMIT ' . max(1, min(100, $limit)),
            ['t' => $entityType, 'id' => $entityId]
        );
    }

    /** @return array<string, mixed>|null */
    public function find(int $revisionId, string $entityType, int $entityId): ?array
    {
        return $this->db->table('revisions')
            ->where('id', $revisionId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->first();
    }

    /** @return array<string, mixed>|null latest autosave newer than $savedAt */
    public function recovery(string $entityType, int $entityId, ?string $savedAt): ?array
    {
        $row = $this->db->first(
            'SELECT * FROM revisions WHERE entity_type = :t AND entity_id = :id AND is_autosave = 1 ORDER BY id DESC LIMIT 1',
            ['t' => $entityType, 'id' => $entityId]
        );
        if ($row === null || $savedAt === null) {
            return $row;
        }

        return $row['created_at'] > $savedAt ? $row : null;
    }

    private function prune(string $entityType, int $entityId, bool $autosave): void
    {
        $max = $autosave ? self::MAX_AUTOSAVES : self::MAX_REVISIONS;
        $rows = $this->db->select(
            'SELECT id FROM revisions WHERE entity_type = :t AND entity_id = :id AND is_autosave = :a ORDER BY id DESC LIMIT 1000',
            ['t' => $entityType, 'id' => $entityId, 'a' => $autosave ? 1 : 0]
        );
        $extra = array_slice(array_column($rows, 'id'), $max);
        foreach ($extra as $id) {
            $this->db->table('revisions')->where('id', $id)->delete();
        }
    }
}
