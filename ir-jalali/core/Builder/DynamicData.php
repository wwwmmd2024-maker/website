<?php

declare(strict_types=1);

namespace IRJalali\Core\Builder;

use IRJalali\App\Repositories\OptionRepository;
use IRJalali\Core\Database\Database;

/**
 * Resolves {{bindings}} against whitelisted providers.
 * NEVER runs raw SQL from binding strings — providers use repositories.
 *
 * Supported: site.*, post.*, author.*, user.*, meta.<key>, date.*
 */
final class DynamicData
{
    public function __construct(
        private readonly Database $db,
        private readonly OptionRepository $options,
    ) {
    }

    public static function isValidBinding(string $binding): bool
    {
        return (bool) preg_match('/^\{\{\s*(site|post|author|user|meta|date)\.[a-z0-9_.]{1,80}\s*\}\}$/i', $binding);
    }

    /**
     * Resolve every {{binding}} inside a string (used for bound fields).
     * Unknown bindings resolve to '' (never leak raw syntax).
     */
    public function resolveString(string $text, RenderContext $ctx): string
    {
        if (!str_contains($text, '{{')) {
            return $text;
        }

        return (string) preg_replace_callback(
            '/\{\{\s*(site|post|author|user|meta|date)\.([a-z0-9_.]{1,80})\s*\}\}/i',
            fn($m) => $this->resolve($m[1], $m[2], $ctx),
            $text
        );
    }

    public function resolve(string $provider, string $key, RenderContext $ctx): string
    {
        return match (strtolower($provider)) {
            'site' => $this->site($key),
            'post' => $this->post($key, $ctx),
            'author' => $this->author($key, $ctx),
            'user' => $this->user($key, $ctx),
            'meta' => $this->meta($key, $ctx),
            'date' => $this->date($key, $ctx),
            default => '',
        };
    }

    private function site(string $key): string
    {
        return match ($key) {
            'title' => (string) $this->options->get('site_title', ''),
            'tagline' => (string) $this->options->get('tagline', ''),
            'url' => '/',
            'year' => date('Y'),
            default => '',
        };
    }

    private function post(string $key, RenderContext $ctx): string
    {
        $post = $ctx->post;
        if ($post === null) {
            return '';
        }

        return match ($key) {
            'title' => (string) ($post['title'] ?? ''),
            'slug' => (string) ($post['slug'] ?? ''),
            'excerpt' => (string) ($post['excerpt'] ?? ''),
            'content' => (string) ($post['content'] ?? ''),
            'featured_image' => (string) ($post['featured_image'] ?? ''),
            'url' => '/' . ltrim((string) ($post['slug'] ?? ''), '/'),
            'published_at' => (string) ($post['published_at'] ?? ''),
            'author' => $this->authorName($post),
            default => '',
        };
    }

    private function author(string $key, RenderContext $ctx): string
    {
        $post = $ctx->post;
        $authorId = $post !== null ? (int) ($post['author_id'] ?? 0) : 0;
        if ($authorId <= 0) {
            return '';
        }
        $author = $this->db->table('users')->where('id', $authorId)->first();
        if ($author === null) {
            return '';
        }

        return match ($key) {
            'name' => (string) ($author['display_name'] ?: $author['username']),
            'email' => (string) $author['email'],
            default => '',
        };
    }

    private function user(string $key, RenderContext $ctx): string
    {
        $user = $ctx->user;
        if ($user === null) {
            return '';
        }

        return match ($key) {
            'name' => (string) ($user['display_name'] ?? $user['username'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            default => '',
        };
    }

    private function meta(string $key, RenderContext $ctx): string
    {
        $post = $ctx->post;
        if ($post === null || !preg_match('/^[a-z0-9_\-]{1,60}$/i', $key)) {
            return '';
        }
        $row = $this->db->table('post_meta')
            ->where('post_id', (int) $post['id'])
            ->where('key', $key)
            ->first();

        return $row !== null ? (string) $row['value'] : '';
    }

    private function date(string $key, RenderContext $ctx): string
    {
        $now = $ctx->timestamp();

        return match ($key) {
            'now' => $now->format('Y-m-d H:i'),
            'today' => $now->format('Y-m-d'),
            'year' => $now->format('Y'),
            default => '',
        };
    }

    /** @param array<string, mixed> $post */
    private function authorName(array $post): string
    {
        $authorId = (int) ($post['author_id'] ?? 0);
        if ($authorId <= 0) {
            return '';
        }
        $author = $this->db->table('users')->where('id', $authorId)->first();
        if ($author === null) {
            return '';
        }

        return (string) ($author['display_name'] ?: $author['username']);
    }
}
