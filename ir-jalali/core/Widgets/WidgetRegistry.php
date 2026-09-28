<?php

declare(strict_types=1);

namespace IRJalali\Core\Widgets;

use IRJalali\Core\Builder\RenderContext;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Logging\Logger;

/**
 * Runtime widget registry + DB catalog sync + sidebar rendering.
 */
final class WidgetRegistry
{
    /** @var array<string, WidgetDefinition> */
    private array $widgets = [];

    public function __construct(
        private readonly Database $db,
        private readonly Logger $logger,
    ) {
    }

    public function register(WidgetDefinition $widget): void
    {
        $this->widgets[$widget->slug] = $widget;
    }

    public function get(string $slug): ?WidgetDefinition
    {
        return $this->widgets[$slug] ?? null;
    }

    /** Drop every runtime widget of a vendor + deactivate its catalog rows. */
    public function unregisterByVendor(string $vendor): int
    {
        $prefix = rtrim($vendor, '/') . '/';
        $count = 0;
        foreach (array_keys($this->widgets) as $slug) {
            if (str_starts_with($slug, $prefix)) {
                unset($this->widgets[$slug]);
                $count++;
            }
        }
        try {
            $this->db->table('widgets')->where('source_slug', rtrim($vendor, '/'))->update(['is_active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable) {
            // Catalog table may not exist yet (installer phase).
        }

        return $count;
    }

    /** @return array<string, WidgetDefinition> */
    public function all(): array
    {
        return $this->widgets;
    }

    /** @param array<string, mixed> $data */
    public function render(string $slug, array $data, RenderContext $ctx): string
    {
        $widget = $this->get($slug);
        if ($widget === null) {
            return '';
        }
        try {
            return $widget->render($data, $ctx);
        } catch (\Throwable $e) {
            $this->logger->channel('widgets')->error('Widget render failed', ['widget' => $slug, 'error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Render every widget instance assigned to a sidebar (ordered).
     * Instance rows store either a `widget` slug or a legacy widget_id.
     */
    public function renderSidebar(string $sidebar, RenderContext $ctx): string
    {
        $instances = $this->db->table('widget_instances')
            ->where('sidebar', $sidebar)
            ->orderBy('ordering')
            ->orderBy('id')
            ->get();
        $html = '';
        foreach ($instances as $instance) {
            $data = json_decode((string) $instance['data_json'], true) ?: [];
            $slug = (string) ($data['widget'] ?? '');
            if ($slug === '' && !empty($instance['widget_id'])) {
                $row = $this->db->table('widgets')->where('id', $instance['widget_id'])->first();
                $slug = $row !== null ? (string) $row['slug'] : '';
            }
            if ($slug === '') {
                continue;
            }
            unset($data['widget']);
            $rendered = $this->render($slug, $data, $ctx);
            if ($rendered !== '') {
                $title = (string) ($data['title'] ?? '');
                $html .= '<div class="ij-widget ij-widget-' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . '">'
                    . ($title !== '' ? '<h4 class="ij-widget-title">' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h4>' : '')
                    . $rendered . '</div>';
            }
        }

        return $html;
    }

    public function syncCatalog(): int
    {
        $count = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($this->widgets as $widget) {
            $existing = $this->db->table('widgets')->where('slug', $widget->slug)->first();
            $row = [
                'name' => mb_substr($widget->title, 0, 140),
                'source' => $widget->source,
                'source_slug' => str_contains($widget->slug, '/') ? explode('/', $widget->slug, 2)[0] : null,
                'schema' => json_encode($widget->schema, JSON_UNESCAPED_UNICODE),
                'is_active' => 1,
                'updated_at' => $now,
            ];
            if ($existing === null) {
                $row['slug'] = $widget->slug;
                $row['created_at'] = $now;
                $this->db->insert('widgets', $row);
            } else {
                $this->db->table('widgets')->where('id', $existing['id'])->update($row);
            }
            $count++;
        }

        return $count;
    }
}
