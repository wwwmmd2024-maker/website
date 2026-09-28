<?php

declare(strict_types=1);

namespace IRJalali\App\Repositories;

use IRJalali\App\Models\Post;
use IRJalali\Core\Database\Database;

/**
 * Content persistence for every post type (page, post, CPTs, ...).
 */
final class PostRepository
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): Post
    {
        $now = date('Y-m-d H:i:s');
        $id = $this->db->insert('posts', [
            'uuid' => $this->uuid(),
            'post_type' => $data['post_type'] ?? 'post',
            'title' => $data['title'],
            'slug' => $data['slug'],
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'] ?? null,
            'content_json' => $data['content_json'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'author_id' => $data['author_id'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'menu_order' => $data['menu_order'] ?? 0,
            'featured_image' => $data['featured_image'] ?? null,
            'template' => $data['template'] ?? null,
            'locale' => $data['locale'] ?? 'fa_IR',
            'published_at' => $data['published_at'] ?? null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $post = $this->find((int) $id);
        if ($post === null) {
            throw new \RuntimeException('Post creation failed.');
        }

        return $post;
    }

    public function find(int $id): ?Post
    {
        $row = $this->db->table('posts')->where('id', $id)->whereNull('deleted_at')->first();

        return $row === null ? null : Post::fromRow($row);
    }

    public function findPublishedBySlug(string $type, string $slug): ?Post
    {
        $row = $this->db->table('posts')
            ->where('post_type', $type)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->first();

        return $row === null ? null : Post::fromRow($row);
    }

    /** @return list<Post> */
    public function published(string $type, int $limit = 10, int $offset = 0): array
    {
        $rows = $this->db->table('posts')
            ->where('post_type', $type)
            ->where('status', 'published')
            ->whereNull('deleted_at')
            ->orderBy('published_at', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return array_map(Post::fromRow(...), $rows);
    }

    public function countByType(string $type, ?string $status = null): int
    {
        $query = $this->db->table('posts')->where('post_type', $type)->whereNull('deleted_at');
        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->count();
    }

    public function slugExists(string $type, string $slug): bool
    {
        return $this->db->table('posts')
            ->where('post_type', $type)
            ->where('slug', $slug)
            ->first() !== null;
    }

    /** Publishes every due scheduled post. Returns affected count. */
    public function publishDueScheduled(string $now): int
    {
        $rows = $this->db->select(
            "SELECT id FROM posts WHERE status = 'scheduled' AND published_at IS NOT NULL AND published_at <= :now AND deleted_at IS NULL",
            ['now' => $now]
        );
        $count = 0;
        foreach ($rows as $row) {
            $count += $this->db->table('posts')->where('id', $row['id'])->update([
                'status' => 'published',
                'updated_at' => $now,
            ]);
        }

        return $count;
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        $allowed = ['title', 'slug', 'excerpt', 'content', 'content_json', 'status', 'author_id',
            'parent_id', 'menu_order', 'featured_image', 'template', 'locale', 'published_at'];
        $update = ['updated_at' => date('Y-m-d H:i:s')];
        foreach ($allowed as $column) {
            if (array_key_exists($column, $data)) {
                $update[$column] = $data[$column];
            }
        }

        return $this->db->table('posts')->where('id', $id)->update($update) > 0;
    }

    public function trash(int $id): bool
    {
        $now = date('Y-m-d H:i:s');

        return $this->db->table('posts')->where('id', $id)->update([
            'deleted_at' => $now,
            'updated_at' => $now,
        ]) > 0;
    }

    public function slugTaken(string $type, string $slug, ?int $ignoreId = null): bool
    {
        $query = $this->db->table('posts')->where('post_type', $type)->where('slug', $slug);
        $rows = $query->get();
        foreach ($rows as $row) {
            if ($ignoreId === null || (int) $row['id'] !== $ignoreId) {
                return true;
            }
        }

        return false;
    }

    /** @return list<Post> */
    public function adminList(string $type, int $limit, int $offset, string $search = '', string $status = ''): array
    {
        $query = $this->db->table('posts')
            ->where('post_type', $type)
            ->whereNull('deleted_at');
        if ($search !== '') {
            $query->where('title', '%' . $search . '%', 'LIKE');
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        $rows = $query->orderBy('id', 'DESC')->limit($limit)->offset($offset)->get();

        return array_map(Post::fromRow(...), $rows);
    }

    public function countAdmin(string $type, string $search = '', string $status = ''): int
    {
        $query = $this->db->table('posts')
            ->where('post_type', $type)
            ->whereNull('deleted_at');
        if ($search !== '') {
            $query->where('title', '%' . $search . '%', 'LIKE');
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        return $query->count();
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
