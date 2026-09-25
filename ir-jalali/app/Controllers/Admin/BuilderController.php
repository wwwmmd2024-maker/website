<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Repositories\MediaRepository;
use IRJalali\App\Repositories\PostRepository;
use IRJalali\App\Services\AuditService;
use IRJalali\App\Services\MediaService;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Blocks\BlockRegistry;
use IRJalali\Core\Builder\ComponentCatalog;
use IRJalali\Core\Builder\Document;
use IRJalali\Core\Builder\GlobalStyles;
use IRJalali\Core\Builder\RenderContext;
use IRJalali\Core\Builder\Renderer;
use IRJalali\Core\Builder\RevisionManager;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Security\Csrf;
use IRJalali\Core\View\View;
use IRJalali\Core\Widgets\WidgetRegistry;

/**
 * Visual builder backend: editor shell + JSON API (load/save/preview/
 * revisions/tokens/media). All endpoints require edit permission.
 */
final class BuilderController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly Document $documents,
        private readonly Renderer $renderer,
        private readonly RevisionManager $revisions,
        private readonly GlobalStyles $styles,
        private readonly Database $db,
        private readonly BlockRegistry $blocks,
        private readonly WidgetRegistry $widgets,
        private readonly PostRepository $posts,
        private readonly MediaRepository $media,
        private readonly MediaService $mediaService,
        private readonly AuditService $audit,
        private readonly Csrf $csrf,
    ) {
        parent::__construct($view, $auth);
    }

    // ── Editor shell ─────────────────────────────────────────────
    public function editor(Request $request): Response
    {
        $entity = (string) $request->route('entity', '');
        $id = (int) $request->route('id', 0);
        if (!Document::isSupported($entity) || $id <= 0) {
            return Response::html($this->view->render('errors.404', ['flash' => $this->pullFlash()]), 404);
        }
        if (($denied = $this->denyUnlessCan($this->editPermission($entity))) !== null) {
            return $denied;
        }
        $doc = $this->documents->load($entity, $id);
        if ($doc === null) {
            return Response::html($this->view->render('errors.404', ['flash' => $this->pullFlash()]), 404);
        }

        return Response::html($this->view->render('admin.builder.editor', [
            'title' => 'ویرایشگر: ' . ($doc['settings']['slug'] ?? $entity . ' #' . $id),
            'boot' => $this->bootPayload($entity, $id, $doc),
        ]));
    }

    // ── JSON API ─────────────────────────────────────────────────
    public function apiLoad(Request $request): Response
    {
        $entity = $request->str('entity');
        $id = (int) $request->input('id', 0);
        if (!Document::isSupported($entity) || $id <= 0) {
            return Response::json(['ok' => false, 'error' => 'bad_request'], 400);
        }
        if (!$this->auth->can($this->editPermission($entity))) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $doc = $this->documents->load($entity, $id);
        if ($doc === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }

        return Response::json(['ok' => true, ...$this->bootPayload($entity, $id, $doc)]);
    }

    public function apiSave(Request $request): Response
    {
        $body = $request->json();
        $entity = (string) ($body['entity'] ?? '');
        $id = (int) ($body['id'] ?? 0);
        $tree = $body['tree'] ?? null;
        if (!Document::isSupported($entity) || $id <= 0 || !is_array($tree)) {
            return Response::json(['ok' => false, 'errors' => ['درخواست نامعتبر است.']], 400);
        }
        if (!$this->auth->can($this->editPermission($entity))) {
            return Response::json(['ok' => false, 'errors' => ['دسترسی ندارید.']], 403);
        }

        $autosave = !empty($body['autosave']);
        $status = isset($body['status']) && is_string($body['status']) ? $body['status'] : null;
        $result = $this->documents->save($entity, $id, $tree, $this->auth->id(), $autosave, $status);
        if (empty($result['ok'])) {
            return Response::json(['ok' => false, 'errors' => $result['errors'] ?? ['خطا در ذخیره‌سازی.']], 422);
        }
        if (!$autosave) {
            $this->audit->audit($this->auth->id(), 'builder.save', $entity, $id, [], ['revision_id' => $result['revision_id'] ?? null]);
        }

        return Response::json(['ok' => true, 'revision_id' => $result['revision_id'] ?? null]);
    }

    public function apiPreview(Request $request): Response
    {
        $body = $request->json();
        $tree = $body['tree'] ?? null;
        if (!is_array($tree) || ($tree['type'] ?? '') === '') {
            return Response::json(['ok' => false, 'error' => 'bad_request'], 400);
        }
        // Preview is allowed for anyone who can edit anything editable.
        if (!$this->auth->can('pages.edit', 'posts.edit')) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $entity = (string) ($body['entity'] ?? 'page');
        $id = (int) ($body['id'] ?? 0);
        $device = (string) ($body['device'] ?? 'desktop');
        if (!in_array($device, ['desktop', 'laptop', 'tablet', 'mobile'], true)) {
            $device = 'desktop';
        }

        $post = null;
        if (in_array($entity, ['page', 'post'], true) && $id > 0) {
            $found = $this->db->table('posts')->where('id', $id)->first();
            if ($found !== null) {
                $post = $found;
            }
        }

        $ctx = new RenderContext(
            user: $this->auth->user(),
            post: $post,
            userRoles: $this->auth->check() ? $this->auth->roles() : [],
            device: $device,
            now: new \DateTimeImmutable(),
            isPreview: true,
        );

        try {
            $output = $this->renderer->render($tree, $ctx);
        } catch (\Throwable $e) {
            return Response::json(['ok' => false, 'error' => 'render_failed'], 500);
        }

        return Response::json(['ok' => true, 'html' => $output->html, 'css' => $output->css]);
    }

    public function apiRevisions(Request $request): Response
    {
        $entity = $request->str('entity');
        $id = (int) $request->input('id', 0);
        if (!Document::isSupported($entity) || $id <= 0) {
            return Response::json(['ok' => false, 'error' => 'bad_request'], 400);
        }
        if (!$this->auth->can($this->editPermission($entity))) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $rows = [];
        foreach ($this->revisions->history($entity, $id, 30) as $row) {
            unset($row['snapshot']); // never ship blobs to the list view
            $rows[] = $row;
        }

        return Response::json(['ok' => true, 'revisions' => $rows]);
    }

    public function apiRevision(Request $request): Response
    {
        $entity = $request->str('entity');
        $id = (int) $request->input('id', 0);
        $revisionId = (int) $request->input('revision', 0);
        if (!Document::isSupported($entity) || $id <= 0 || $revisionId <= 0) {
            return Response::json(['ok' => false, 'error' => 'bad_request'], 400);
        }
        if (!$this->auth->can($this->editPermission($entity))) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $row = $this->revisions->find($revisionId, $entity, $id);
        if ($row === null) {
            return Response::json(['ok' => false, 'error' => 'not_found'], 404);
        }
        $snapshot = json_decode((string) $row['snapshot'], true);
        $tree = is_array($snapshot) ? ($snapshot['root'] ?? $snapshot) : null;
        if (!is_array($tree)) {
            return Response::json(['ok' => false, 'error' => 'corrupt'], 422);
        }

        return Response::json(['ok' => true, 'tree' => $tree]);
    }

    public function apiRestore(Request $request): Response
    {
        $body = $request->json();
        $entity = (string) ($body['entity'] ?? '');
        $id = (int) ($body['id'] ?? 0);
        $revisionId = (int) ($body['revision_id'] ?? 0);
        if (!Document::isSupported($entity) || $id <= 0 || $revisionId <= 0) {
            return Response::json(['ok' => false, 'errors' => ['درخواست نامعتبر است.']], 400);
        }
        if (!$this->auth->can($this->editPermission($entity))) {
            return Response::json(['ok' => false, 'errors' => ['دسترسی ندارید.']], 403);
        }

        $result = $this->documents->restoreRevision($entity, $id, $revisionId, $this->auth->id());
        if (empty($result['ok'])) {
            return Response::json(['ok' => false, 'errors' => $result['errors'] ?? ['بازیابی ناموفق بود.']], 422);
        }
        $this->audit->audit($this->auth->id(), 'builder.restore', $entity, $id, [], ['revision_id' => $revisionId]);
        $doc = $this->documents->load($entity, $id);

        return Response::json(['ok' => true, 'doc' => $doc]);
    }

    public function apiTokens(Request $request): Response
    {
        if (!$this->auth->can('pages.edit', 'posts.edit')) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        return Response::json(['ok' => true, 'tokens' => $this->styles->all(), 'css' => $this->styles->cssVariables()]);
    }

    public function apiTokensSave(Request $request): Response
    {
        if (!$this->auth->can('pages.publish')) {
            return Response::json(['ok' => false, 'errors' => ['فقط ناشران می‌توانند استایل سراسری را تغییر دهند.']], 403);
        }
        $tokens = $request->json()['tokens'] ?? null;
        if (!is_array($tokens)) {
            return Response::json(['ok' => false, 'errors' => ['درخواست نامعتبر است.']], 400);
        }

        try {
            $this->styles->save($tokens);
        } catch (\Throwable) {
            return Response::json(['ok' => false, 'errors' => ['ذخیره توکن‌ها ناموفق بود.']], 422);
        }
        $this->audit->audit($this->auth->id(), 'builder.tokens', null, null, [], []);

        return Response::json(['ok' => true, 'css' => $this->styles->cssVariables()]);
    }

    public function apiMedia(Request $request): Response
    {
        if (!$this->auth->can('media.view')) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $items = [];
        foreach ($this->media->latest(60) as $row) {
            if (!str_starts_with((string) ($row['mime'] ?? ''), 'image/')) {
                continue;
            }
            $items[] = [
                'id' => (int) $row['id'],
                'name' => (string) ($row['original_name'] ?? $row['filename']),
                'url' => $this->mediaService->publicUrl($row),
                'thumb' => $this->mediaService->publicUrl($row, 'thumb'),
                'alt' => (string) ($row['alt'] ?? ''),
            ];
        }

        return Response::json(['ok' => true, 'items' => $items]);
    }

    // ── Payload assembly ─────────────────────────────────────────
    /** @return array<string, mixed> */
    private function bootPayload(string $entity, int $id, array $doc): array
    {
        return [
            'entity' => $entity,
            'id' => $id,
            'doc' => $doc,
            'csrf' => $this->csrf->token(),
            'canPublish' => $this->auth->can('pages.publish'),
            'canUseHtml' => $this->auth->can('pages.publish'),
            'components' => ComponentCatalog::all(),
            'categories' => ComponentCatalog::CATEGORIES,
            'styleGroups' => ComponentCatalog::styleGroups(),
            'animations' => ComponentCatalog::ANIMATIONS,
            'conditionRules' => ComponentCatalog::CONDITION_RULES,
            'bindings' => ComponentCatalog::DYNAMIC_BINDINGS,
            'icons' => ComponentCatalog::ICONS,
            'blocks' => $this->blockOptions(),
            'widgets' => $this->widgetOptions(),
            'forms' => $this->formOptions(),
            'menus' => $this->menuOptions(),
            'postTypes' => $this->postTypeOptions(),
            'taxonomies' => $this->taxonomyOptions(),
            'roles' => $this->roleOptions(),
            'tokens' => $this->styles->all(),
            'tokensCss' => $this->styles->cssVariables(),
            'breakpoints' => ['desktop' => 'دسکتاپ', 'laptop' => 'لپ‌تاپ', 'tablet' => 'تبلت', 'mobile' => 'موبایل'],
            'frontCssUrl' => '/assets/css/builder-front.css',
        ];
    }

    private function editPermission(string $entity): string
    {
        // Site templates are page-like; same editorial permission.
        return $entity === 'post' ? 'posts.edit' : 'pages.edit';
    }

    /** @return list<array<string, mixed>> */
    private function blockOptions(): array
    {
        $out = [];
        foreach ($this->blocks->all() as $block) {
            if ($block->permission !== null && !$this->auth->can($block->permission)) {
                continue;
            }
            $out[] = $block->metadata();
        }
        usort($out, fn ($a, $b): int => strcmp((string) $a['title'], (string) $b['title']));

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function widgetOptions(): array
    {
        $out = [];
        foreach ($this->widgets->all() as $widget) {
            $out[] = $widget->metadata();
        }
        usort($out, fn ($a, $b): int => strcmp((string) $a['title'], (string) $b['title']));

        return $out;
    }

    /** @return list<array{id: int, title: string}> */
    private function formOptions(): array
    {
        $out = [];
        try {
            foreach ($this->db->table('forms')->where('is_active', 1)->orderBy('title')->get() as $row) {
                $out[] = ['id' => (int) $row['id'], 'title' => (string) $row['title']];
            }
        } catch (\Throwable) {
            // Forms table lives behind installer; picker degrades to presets.
        }

        return $out;
    }

    /** @return list<array{id: int, name: string, location: string}> */
    private function menuOptions(): array
    {
        $out = [];
        foreach ($this->db->table('menus')->orderBy('id')->get() as $row) {
            $out[] = ['id' => (int) $row['id'], 'name' => (string) ($row['name'] ?? ('#' . $row['id'])), 'location' => (string) ($row['location'] ?? '')];
        }

        return $out;
    }

    /** @return list<array{slug: string, name: string}> */
    private function postTypeOptions(): array
    {
        $out = [];
        foreach ($this->db->table('post_types')->orderBy('slug')->get() as $row) {
            $out[] = ['slug' => (string) $row['slug'], 'name' => (string) $row['name']];
        }

        return $out;
    }

    /** @return list<array{slug: string, name: string, terms: list<array{id: int, slug: string, name: string}>}> */
    private function taxonomyOptions(): array
    {
        $out = [];
        foreach ($this->db->table('taxonomies')->orderBy('slug')->get() as $tax) {
            $terms = [];
            foreach ($this->db->table('terms')->where('taxonomy_id', $tax['id'])->orderBy('name')->get() as $term) {
                $terms[] = ['id' => (int) $term['id'], 'slug' => (string) $term['slug'], 'name' => (string) $term['name']];
            }
            $out[] = ['slug' => (string) $tax['slug'], 'name' => (string) $tax['name'], 'terms' => $terms];
        }

        return $out;
    }

    /** @return list<array{slug: string, name: string}> */
    private function roleOptions(): array
    {
        $out = [];
        try {
            foreach ($this->db->table('roles')->orderBy('name')->get() as $row) {
                $out[] = ['slug' => (string) $row['slug'], 'name' => (string) ($row['name'] ?? $row['slug'])];
            }
        } catch (\Throwable) {
            $out = [
                ['slug' => 'administrator', 'name' => 'مدیر'],
                ['slug' => 'editor', 'name' => 'ویرایشگر'],
                ['slug' => 'subscriber', 'name' => 'مشترک'],
            ];
        }

        return $out;
    }
}
