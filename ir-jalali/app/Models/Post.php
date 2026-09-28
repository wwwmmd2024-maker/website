<?php

declare(strict_types=1);

namespace IRJalali\App\Models;

/**
 * Immutable Post/Page value object.
 */
final class Post
{
    /** @param array<string, mixed> $row */
    private function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly string $type,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $excerpt,
        public readonly ?string $content,
        public readonly string $status,
        public readonly ?int $authorId,
        public readonly ?string $featuredImage,
        public readonly ?string $template,
        public readonly ?string $publishedAt,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
        public readonly array $row,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['uuid'],
            (string) $row['post_type'],
            (string) $row['title'],
            (string) $row['slug'],
            $row['excerpt'] ?? null,
            $row['content'] ?? null,
            (string) $row['status'],
            isset($row['author_id']) ? (int) $row['author_id'] : null,
            $row['featured_image'] ?? null,
            $row['template'] ?? null,
            $row['published_at'] ?? null,
            $row['created_at'] ?? null,
            $row['updated_at'] ?? null,
            $row,
        );
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    /** @return array<string, mixed> public-safe representation for the API. */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'type' => $this->type,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'featured_image' => $this->featuredImage,
            'published_at' => $this->publishedAt,
        ];
    }
}
