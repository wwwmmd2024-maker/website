<?php

declare(strict_types=1);

namespace IRJalali\App\Repositories;

use IRJalali\Core\Database\Database;

final class MediaRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    public function create(array $data): array
    {
        $now = date('Y-m-d H:i:s');
        $id = $this->db->insert('media', [
            'uuid' => $data['uuid'],
            'filename' => $data['filename'],
            'original_name' => $data['original_name'],
            'mime' => $data['mime'],
            'extension' => $data['extension'],
            'size_bytes' => $data['size_bytes'] ?? 0,
            'folder' => $data['folder'] ?? '/',
            'disk' => 'uploads',
            'width' => $data['width'] ?? 0,
            'height' => $data['height'] ?? 0,
            'alt' => $data['alt'] ?? null,
            'uploaded_by' => $data['uploaded_by'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $this->find((int) $id) ?? throw new \RuntimeException('Media creation failed.');
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db->table('media')->where('id', $id)->whereNull('deleted_at')->first();
    }

    /** @return list<array<string, mixed>> */
    public function latest(int $limit = 60): array
    {
        return $this->db->table('media')
            ->whereNull('deleted_at')
            ->orderBy('id', 'DESC')
            ->limit($limit)
            ->get();
    }

    public function count(): int
    {
        return $this->db->table('media')->whereNull('deleted_at')->count();
    }

    public function softDelete(int $id): void
    {
        $this->db->table('media')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }
}
