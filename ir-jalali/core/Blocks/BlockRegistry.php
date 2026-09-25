<?php

declare(strict_types=1);

namespace IRJalali\Core\Blocks;

use IRJalali\Core\Builder\RenderContext;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Logging\Logger;

/**
 * Runtime block registry (code-first) + DB catalog sync for admin UI.
 */
final class BlockRegistry
{
    /** @var array<string, BlockDefinition> */
    private array $blocks = [];

    public function __construct(
        private readonly Database $db,
        private readonly Logger $logger,
    ) {
    }

    public function register(BlockDefinition $block): void
    {
        if (!preg_match('#^[a-z0-9_\-]+/[a-z0-9_\-]+$#i', $block->slug) && !str_contains($block->slug, '/')) {
            // Allow core slugs without vendor prefix only from source=core.
            if ($block->source !== 'core') {
                throw new \InvalidArgumentException("Block slug [{$block->slug}] must be vendor/name.");
            }
        }
        $this->blocks[$block->slug] = $block;
    }

    public function get(string $slug): ?BlockDefinition
    {
        return $this->blocks[$slug] ?? null;
    }

    /**
     * Drop every runtime block of a vendor + deactivate its catalog rows.
     * Used on plugin uninstall.
     */
    public function unregisterByVendor(string $vendor): int
    {
        $prefix = rtrim($vendor, '/') . '/';
        $count = 0;
        foreach (array_keys($this->blocks) as $slug) {
            if (str_starts_with($slug, $prefix)) {
                unset($this->blocks[$slug]);
                $count++;
            }
        }
        try {
            $this->db->table('blocks')->where('source_slug', rtrim($vendor, '/'))->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
            // Catalog table may not exist yet (installer phase).
        }

        return $count;
    }

    /** @return array<string, BlockDefinition> */
    public function all(?string $category = null): array
    {
        if ($category === null) {
            return $this->blocks;
        }

        return array_filter($this->blocks, fn(BlockDefinition $b) => $b->category === $category);
    }

    /** @return list<string> */
    public function categories(): array
    {
        $cats = [];
        foreach ($this->blocks as $block) {
            $cats[$block->category] = true;
        }

        return array_keys($cats);
    }

    /** @param array<string, mixed> $data */
    public function render(string $slug, array $data, RenderContext $ctx, ?callable $can = null): string
    {
        $block = $this->get($slug);
        if ($block === null) {
            return $ctx->isPreview ? '<div class="ij-missing">بلاک یافت نشد: ' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '</div>' : '';
        }
        if ($block->permission !== null && $can !== null && !$can($block->permission)) {
            return '';
        }
        try {
            return $block->render($data, $ctx);
        } catch (\Throwable $e) {
            $this->logger->channel('blocks')->error('Block render failed', ['block' => $slug, 'error' => $e->getMessage()]);

            return $ctx->isPreview ? '<div class="ij-missing">خطا در رندر بلاک: ' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '</div>' : '';
        }
    }

    /**
     * Sync runtime definitions into the `blocks` catalog table (admin UI).
     * Called after core boot and on plugin activation — never on public reads.
     */
    public function syncCatalog(): int
    {
        $count = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($this->blocks as $block) {
            $existing = $this->db->table('blocks')->where('slug', $block->slug)->first();
            $row = [
                'name' => mb_substr($block->title, 0, 140),
                'category' => mb_substr($block->category, 0, 50),
                'source' => $block->source,
                'source_slug' => str_contains($block->slug, '/') ? explode('/', $block->slug, 2)[0] : null,
                'schema' => json_encode($block->schema, JSON_UNESCAPED_UNICODE),
                'defaults' => json_encode($block->defaults, JSON_UNESCAPED_UNICODE),
                'is_active' => 1,
                'updated_at' => $now,
            ];
            if ($existing === null) {
                $row['slug'] = $block->slug;
                $row['created_at'] = $now;
                $this->db->insert('blocks', $row);
            } else {
                $this->db->table('blocks')->where('id', $existing['id'])->update($row);
            }
            $count++;
        }

        return $count;
    }
}
