<?php

declare(strict_types=1);

namespace IRJalali\Core\Builder;

use IRJalali\Core\Database\Database;

/**
 * Builder document persistence.
 * Entities: page | post (posts.content_json) and template (templates.content_json).
 * Header/footer are templates with type header/footer (site-header/site-footer).
 */
final class Document
{
    public const ENTITIES = ['page', 'post', 'template'];

    public function __construct(
        private readonly Database $db,
        private readonly TreeValidator $validator,
        private readonly RevisionManager $revisions,
    ) {
    }

    public static function isSupported(string $entity): bool
    {
        return in_array($entity, self::ENTITIES, true);
    }

    /** @return array{title: string, tree: array<string, mixed>, settings: array<string, mixed>, updated_at: string|null, status: string|null}|null */
    public function load(string $entity, int $id): ?array
    {
        if (!self::isSupported($entity)) {
            return null;
        }
        if ($entity === 'template') {
            $row = $this->db->table('templates')->where('id', $id)->first();
            if ($row === null) {
                return null;
            }

            return [
                'title' => (string) $row['name'],
                'tree' => $this->decodeTree((string) ($row['content_json'] ?? '')),
                'settings' => ['slug' => $row['slug'], 'type' => $row['type']],
                'updated_at' => $row['updated_at'] ?? null,
                'status' => 'published',
            ];
        }

        $row = $this->db->table('posts')
            ->where('id', $id)
            ->where('post_type', $entity)
            ->whereNull('deleted_at')
            ->first();
        if ($row === null) {
            return null;
        }

        return [
            'title' => (string) $row['title'],
            'tree' => $this->decodeTree((string) ($row['content_json'] ?? '')),
            'settings' => ['slug' => $row['slug'], 'template' => $row['template']],
            'updated_at' => $row['updated_at'] ?? null,
            'status' => (string) $row['status'],
        ];
    }

    /**
     * @param array<string, mixed> $tree raw client tree
     * @return array{ok: bool, revision_id?: int, errors?: list<string>}
     */
    public function save(string $entity, int $id, array $tree, ?int $userId, bool $autosave = false, ?string $status = null): array
    {
        if (!self::isSupported($entity)) {
            return ['ok' => false, 'errors' => ['Unsupported entity.']];
        }
        $result = $this->validator->validate($tree);
        if (!$result['ok']) {
            return $result;
        }
        $json = json_encode(['version' => 1, 'root' => $result['tree']], JSON_UNESCAPED_UNICODE);
        if ($json === false || strlen($json) > 2_000_000) {
            return ['ok' => false, 'errors' => ['Document too large.']];
        }

        $now = date('Y-m-d H:i:s');
        $revisionId = $this->revisions->snapshot($entity, $id, $userId, $autosave ? 'autosave' : 'manual', $json, $autosave);

        if ($entity === 'template') {
            $this->db->table('templates')->where('id', $id)->update(['content_json' => $json, 'updated_at' => $now]);
        } else {
            $update = ['content_json' => $json, 'updated_at' => $now];
            if ($status !== null && in_array($status, ['draft', 'pending', 'published', 'private'], true)) {
                $update['status'] = $status;
                if ($status === 'published') {
                    $update['published_at'] = $now;
                }
            }
            $this->db->table('posts')->where('id', $id)->update($update);
        }

        return ['ok' => true, 'revision_id' => $revisionId];
    }

    /** @return array{ok: bool, errors?: list<string>} */
    public function restoreRevision(string $entity, int $id, int $revisionId, ?int $userId): array
    {
        $revision = $this->revisions->find($revisionId, $entity, $id);
        if ($revision === null) {
            return ['ok' => false, 'errors' => ['Revision not found.']];
        }
        $snapshot = json_decode((string) $revision['snapshot'], true);
        $tree = is_array($snapshot) ? ($snapshot['root'] ?? $snapshot) : null;
        if (!is_array($tree)) {
            return ['ok' => false, 'errors' => ['Corrupt revision snapshot.']];
        }

        return $this->save($entity, $id, $tree, $userId);
    }

    /** @return array<string, mixed> */
    public function decodeTree(string $json): array
    {
        if ($json === '') {
            return $this->starterTree();
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $this->starterTree();
        }
        $root = $decoded['root'] ?? $decoded;
        if (!is_array($root) || empty($root['type'])) {
            return $this->starterTree();
        }

        return $root;
    }

    /** @return array<string, mixed> */
    public function starterTree(string $title = 'صفحه جدید'): array
    {
        return [
            'id' => 'n_root',
            'type' => 'section',
            'content' => [],
            'children' => [
                [
                    'id' => 'n_container',
                    'type' => 'container',
                    'content' => [],
                    'children' => [
                        ['id' => 'n_heading', 'type' => 'heading', 'content' => ['level' => 1, 'text' => $title], 'children' => []],
                        ['id' => 'n_text', 'type' => 'text', 'content' => ['text' => 'این متن را ویرایش کنید یا از پنل سمت چپ المان اضافه کنید.'], 'children' => []],
                    ],
                ],
            ],
        ];
    }

    /** Ensure site header/footer template rows exist. Returns [headerId, footerId]. */
    public function ensureSiteParts(): array
    {
        $header = $this->ensureTemplate('site-header', 'سربرگ سایت', 'header', $this->defaultHeaderTree());
        $footer = $this->ensureTemplate('site-footer', 'پاورقی سایت', 'footer', $this->defaultFooterTree());

        return [$header, $footer];
    }

    /** @param array<string, mixed> $tree */
    private function ensureTemplate(string $slug, string $name, string $type, array $tree): int
    {
        $existing = $this->db->table('templates')->where('slug', $slug)->first();
        if ($existing !== null) {
            return (int) $existing['id'];
        }
        $now = date('Y-m-d H:i:s');

        return (int) $this->db->insert('templates', [
            'slug' => $slug,
            'name' => $name,
            'type' => $type,
            'content_json' => json_encode(['version' => 1, 'root' => $tree], JSON_UNESCAPED_UNICODE),
            'is_default' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** @return array<string, mixed> */
    private function defaultHeaderTree(): array
    {
        return [
            'id' => 'n_root', 'type' => 'section', 'content' => [],
            'style' => ['background' => '#141a38', 'paddingTop' => '14px', 'paddingBottom' => '14px'],
            'children' => [[
                'id' => 'n_hwrap', 'type' => 'container', 'content' => [],
                'style' => ['display' => 'flex', 'justifyContent' => 'space-between', 'alignItems' => 'center', 'gap' => '16px'],
                'children' => [
                    ['id' => 'n_logo', 'type' => 'heading', 'content' => ['level' => 3, 'text' => '{{site.title}}'],
                     'style' => ['color' => '#ffffff'], 'children' => []],
                    ['id' => 'n_nav', 'type' => 'menu', 'content' => ['location' => 'primary'], 'children' => []],
                ],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    private function defaultFooterTree(): array
    {
        return [
            'id' => 'n_root', 'type' => 'section', 'content' => [],
            'style' => ['background' => '#141a38', 'paddingTop' => '26px', 'paddingBottom' => '26px', 'marginTop' => '40px'],
            'children' => [[
                'id' => 'n_ftext', 'type' => 'text', 'content' => ['text' => 'قدرت‌گرفته از IR-Jalali'],
                'style' => ['color' => '#8b93c9', 'textAlign' => 'center'], 'children' => [],
            ]],
        ];
    }
}
