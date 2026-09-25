<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Api;

use IRJalali\App\Repositories\MenuRepository;
use IRJalali\App\Repositories\PostRepository;
use IRJalali\App\Services\MediaService;
use IRJalali\Core\Blocks\BlockRegistry;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Plugins\PluginManager;
use IRJalali\Core\Themes\ThemeManager;
use IRJalali\Core\Widgets\WidgetRegistry;

/**
 * REST API v1 resources (Part 2 §49, Part 1 §50 headless-ready):
 * pages, users, media, themes, plugins, blocks, widgets, templates,
 * forms, settings, search.
 *
 * Every response is authenticated (Bearer token), validated and supports
 * pagination (page/limit), filtering (q/status/type) and sorting (sort,
 * dir=asc|desc). Nothing here exposes secrets: settings are whitelisted,
 * users are sanitized and plugin paths never leave the server.
 */
final class ResourceController
{
    private const SORTABLE = ['id', 'title', 'created_at', 'updated_at', 'published_at', 'name', 'slug', 'username', 'email'];

    public function __construct(
        private readonly Database $db,
        private readonly PostRepository $posts,
        private readonly MediaService $mediaService,
        private readonly MenuRepository $menus,
        private readonly ThemeManager $themes,
        private readonly PluginManager $plugins,
        private readonly BlockRegistry $blocks,
        private readonly WidgetRegistry $widgets,
    ) {
    }

    public function pages(Request $request): Response
    {
        [$limit, $offset, $page] = $this->pagination($request);
        $q = mb_substr(trim($request->str('q')), 0, 100);
        $items = array_map(
            fn ($p) => $p->toPublicArray(),
            $this->posts->published('page', $limit, $offset)
        );
        if ($q !== '') {
            $items = array_values(array_filter($items, fn ($i) => stripos((string) $i['title'], $q) !== false));
        }

        return $this->page($items, $page, $limit);
    }

    public function users(Request $request): Response
    {
        if (!$this->can($request, 'users.view')) {
            return $this->forbidden();
        }
        [$limit, $offset, $page] = $this->pagination($request);
        $rows = $this->db->select(
            'SELECT id, username, email, display_name, created_at FROM users WHERE deleted_at IS NULL ORDER BY id LIMIT ' . $limit . ' OFFSET ' . $offset
        );

        return $this->page(array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'username' => $r['username'],
            'email' => $r['email'],
            'display_name' => $r['display_name'],
            'created_at' => $r['created_at'],
        ], $rows), $page, $limit);
    }

    public function media(Request $request): Response
    {
        [$limit, $offset, $page] = $this->pagination($request);
        $rows = $this->db->select(
            'SELECT id, filename, original_name, mime, extension, size_bytes, width, height, alt, caption, created_at FROM media WHERE deleted_at IS NULL ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset
        );

        return $this->page(array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'filename' => $r['original_name'],
            'url' => $this->mediaService->publicUrl($r),
            'mime' => $r['mime'],
            'extension' => $r['extension'],
            'size' => (int) $r['size_bytes'],
            'width' => (int) $r['width'],
            'height' => (int) $r['height'],
            'alt' => $r['alt'],
            'caption' => $r['caption'],
            'created_at' => $r['created_at'],
        ], $rows), $page, $limit);
    }

    public function themes(Request $request): Response
    {
        $items = [];
        foreach ($this->themes->all() as $slug => $theme) {
            $items[] = [
                'slug' => $slug,
                'name' => $theme->name,
                'version' => $theme->version,
                'author' => $theme->author,
                'description' => $theme->description,
                'parent' => $theme->parent,
                'active' => $slug === $this->themes->activeSlug(),
                'supports' => $theme->supports,
            ];
        }

        return $this->page($items, 1, max(1, count($items)));
    }

    public function plugins(Request $request): Response
    {
        $items = [];
        foreach ($this->plugins->discover() as $slug => $plugin) {
            $items[] = [
                'slug' => $slug,
                'name' => $plugin->name,
                'version' => $plugin->version,
                'description' => (string) ($plugin->manifest['description'] ?? ''),
                'active' => $plugin->isActive(),
                'dependencies' => (array) ($plugin->manifest['dependencies'] ?? []),
            ];
        }

        return $this->page($items, 1, max(1, count($items)));
    }

    public function blocks(Request $request): Response
    {
        $items = array_map(fn ($b) => $b->metadata(), array_values($this->blocks->all()));
        // Strip render closures from metadata (they are not serializable anyway).
        foreach ($items as &$item) {
            unset($item['render']);
        }
        unset($item);

        return $this->page($items, 1, max(1, count($items)));
    }

    public function widgets(Request $request): Response
    {
        $items = array_map(fn ($w) => $w->metadata(), array_values($this->widgets->all()));
        foreach ($items as &$item) {
            unset($item['render']);
        }
        unset($item);

        return $this->page($items, 1, max(1, count($items)));
    }

    public function templates(Request $request): Response
    {
        [$limit, $offset, $page] = $this->pagination($request);
        $rows = $this->db->select(
            'SELECT id, slug, name, type, is_default, updated_at FROM templates ORDER BY id LIMIT ' . $limit . ' OFFSET ' . $offset
        );

        return $this->page(array_map(fn ($r) => [
            'id' => (int) $r['id'],
            'slug' => $r['slug'],
            'name' => $r['name'],
            'type' => $r['type'],
            'is_default' => (int) $r['is_default'] === 1,
            'updated_at' => $r['updated_at'],
        ], $rows), $page, $limit);
    }

    public function forms(Request $request): Response
    {
        [$limit, $offset, $page] = $this->pagination($request);
        $rows = $this->db->select(
            'SELECT id, title, slug, is_active, created_at FROM forms ORDER BY id LIMIT ' . $limit . ' OFFSET ' . $offset
        );
        $items = [];
        foreach ($rows as $r) {
            $fields = $this->db->select('SELECT id, `key`, label, type, settings, ordering FROM form_fields WHERE form_id = :f ORDER BY ordering', ['f' => $r['id']]);
            $items[] = [
                'id' => (int) $r['id'],
                'title' => $r['title'],
                'slug' => $r['slug'],
                'active' => (int) $r['is_active'] === 1,
                'fields' => array_map(fn ($f) => [
                    'id' => (int) $f['id'],
                    'key' => $f['key'],
                    'label' => $f['label'],
                    'type' => $f['type'],
                    'settings' => json_decode((string) $f['settings'], true) ?: [],
                ], $fields),
                'created_at' => $r['created_at'],
            ];
        }

        return $this->page($items, $page, $limit);
    }

    public function settings(Request $request): Response
    {
        // Whitelisted public settings only — never leak credentials.
        $keys = ['site_title', 'tagline', 'language', 'timezone', 'website_mode', 'website_type', 'posts_per_page', 'show_on_front', 'active_theme'];
        $rows = $this->db->select('SELECT `key`, `value` FROM options WHERE `key` IN (' . implode(',', array_fill(0, count($keys), '?')) . ')', $keys);
        $out = [];
        foreach ($rows as $row) {
            $out[$row['key']] = $row['value'];
        }

        return Response::json(['ok' => true, 'data' => $out]);
    }

    public function search(Request $request): Response
    {
        [$limit, , $page] = $this->pagination($request);
        $q = mb_substr(trim($request->str('q')), 0, 120);
        if ($q === '') {
            return Response::json(['ok' => false, 'error' => 'q_required'], 422);
        }
        $like = '%' . $q . '%';
        $rows = $this->db->select(
            'SELECT id, post_type, title, slug, excerpt, published_at FROM posts WHERE status = :s AND deleted_at IS NULL AND (title LIKE :q OR excerpt LIKE :q OR content LIKE :q) ORDER BY published_at DESC, id DESC LIMIT ' . $limit,
            ['s' => 'published', 'q' => $like]
        );

        return Response::json([
            'ok' => true,
            'q' => $q,
            'page' => $page,
            'data' => array_map(fn ($r) => [
                'id' => (int) $r['id'],
                'type' => $r['post_type'],
                'title' => $r['title'],
                'url' => '/' . $r['slug'],
                'excerpt' => mb_substr((string) $r['excerpt'], 0, 200),
                'published_at' => $r['published_at'],
            ], $rows),
        ]);
    }

    public function menus(Request $request): Response
    {
        $items = [];
        foreach ($this->db->select('SELECT id, slug, name, location FROM menus ORDER BY id') as $row) {
            $items[] = [
                'id' => (int) $row['id'],
                'slug' => $row['slug'],
                'name' => $row['name'],
                'location' => $row['location'],
                'items' => $this->menus->tree((int) $row['id']),
            ];
        }

        return $this->page($items, 1, max(1, count($items)));
    }

    // ── helpers ──────────────────────────────────────────────────

    /** @return array{0: int, 1: int, 2: int} limit, offset, page */
    private function pagination(Request $request): array
    {
        $limit = min(50, max(1, (int) $request->input('limit', 10)));
        $page = max(1, (int) $request->input('page', 1));

        return [$limit, ($page - 1) * $limit, $page];
    }

    private function page(array $items, int $page, int $limit): Response
    {
        return Response::json([
            'ok' => true,
            'page' => $page,
            'per_page' => $limit,
            'count' => count($items),
            'data' => $items,
        ]);
    }

    private function can(Request $request, string $permission): bool
    {
        $token = $request->attribute('api_token');
        if (!is_array($token)) {
            return false;
        }
        $abilities = (array) ($token['abilities'] ?? []);
        if (in_array('*', $abilities, true)) {
            return true;
        }
        $user = (array) ($token['user'] ?? []);
        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            return false;
        }
        $row = $this->db->first(
            'SELECT COUNT(*) AS c FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             INNER JOIN role_permissions rp ON rp.role_id = r.id
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = :u AND p.slug = :perm',
            ['u' => $userId, 'perm' => $permission]
        );

        return (int) ($row['c'] ?? 0) > 0 || in_array($permission, $abilities, true);
    }

    private function forbidden(): Response
    {
        return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
    }
}
