<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Repositories\PostRepository;
use IRJalali\App\Support\CustomFieldRenderer;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Security\Sanitize;
use IRJalali\Core\Validation\Validator;
use IRJalali\Core\View\View;

/**
 * Content CRUD for any Custom Post Type created in the admin (Part 1 §10).
 * Custom field values are persisted in `post_meta` (Part 1 §11).
 */
final class CptController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly Database $db,
        private readonly PostRepository $posts,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('posts.view', 'pages.view', 'settings.manage')) {
            return $denied;
        }
        $type = $this->resolveType((string) $request->route('type', ''));
        if ($type === null) {
            return Response::html($this->view->render('errors.404', ['flash' => $this->pullFlash()]), 404);
        }
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;

        return $this->render('admin.cpt.index', [
            'type' => $type,
            'items' => $this->posts->adminList($type['slug'], $perPage, ($page - 1) * $perPage, $request->str('q'), ''),
            'total' => $this->posts->countAdmin($type['slug']),
            'page' => $page,
            'user' => $this->auth->user(),
        ]);
    }

    public function create(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('posts.create', 'settings.manage')) {
            return $denied;
        }
        $type = $this->resolveType((string) $request->route('type', ''));
        if ($type === null) {
            return Response::html($this->view->render('errors.404', ['flash' => $this->pullFlash()]), 404);
        }

        return $this->render('admin.cpt.form', [
            'type' => $type,
            'item' => null,
            'fields' => $this->fieldsFor($type['slug']),
            'values' => [],
            'renderer' => new CustomFieldRenderer($this->db),
            'user' => $this->auth->user(),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('posts.create', 'settings.manage')) {
            return $denied;
        }
        $type = $this->resolveType((string) $request->route('type', ''));
        if ($type === null) {
            return Response::html($this->view->render('errors.404', ['flash' => $this->pullFlash()]), 404);
        }
        $validator = Validator::make($request->only('title', 'slug'), ['title' => 'required|max:191', 'slug' => 'required|max:191']);
        if ($validator->fails()) {
            $this->withFlash('error', 'عنوان و نامک الزامی است.');

            return $this->redirect('/admin/cpt/' . $type['slug'] . '/create');
        }
        $slug = Sanitize::slug($request->str('slug'));
        if ($this->posts->slugTaken($type['slug'], $slug)) {
            $slug .= '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }

        $post = $this->posts->create([
            'post_type' => $type['slug'],
            'title' => $request->str('title'),
            'slug' => $slug,
            'excerpt' => $request->str('excerpt') !== '' ? $request->str('excerpt') : null,
            'content' => $request->str('content') !== '' ? $request->str('content') : null,
            'status' => in_array($request->str('status'), ['draft', 'published'], true) ? $request->str('status') : 'draft',
            'author_id' => $this->auth->id(),
            'published_at' => $request->str('status') === 'published' ? date('Y-m-d H:i:s') : null,
        ]);
        $this->saveFieldValues($post->id, $type['slug'], $request);
        $this->withFlash('success', 'آیتم ساخته شد.');

        return $this->redirect('/admin/cpt/' . $type['slug']);
    }

    public function edit(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('posts.edit', 'settings.manage')) {
            return $denied;
        }
        $type = $this->resolveType((string) $request->route('type', ''));
        $item = $this->posts->find((int) $request->route('id', 0));
        if ($type === null || $item === null || $item->type !== $type['slug']) {
            return Response::html($this->view->render('errors.404', ['flash' => $this->pullFlash()]), 404);
        }

        return $this->render('admin.cpt.form', [
            'type' => $type,
            'item' => $item,
            'fields' => $this->fieldsFor($type['slug']),
            'values' => $this->metaValues($item->id),
            'renderer' => new CustomFieldRenderer($this->db),
            'user' => $this->auth->user(),
        ]);
    }

    public function update(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('posts.edit', 'settings.manage')) {
            return $denied;
        }
        $type = $this->resolveType((string) $request->route('type', ''));
        $item = $this->posts->find((int) $request->route('id', 0));
        if ($type === null || $item === null || $item->type !== $type['slug']) {
            return Response::html($this->view->render('errors.404', ['flash' => $this->pullFlash()]), 404);
        }
        $status = in_array($request->str('status'), ['draft', 'published'], true) ? $request->str('status') : $item->status;
        $this->posts->update($item->id, [
            'title' => $request->str('title', $item->title),
            'excerpt' => $request->str('excerpt') !== '' ? $request->str('excerpt') : null,
            'content' => $request->str('content') !== '' ? $request->str('content') : null,
            'status' => $status,
            'published_at' => $status === 'published' ? ($item->publishedAt ?? date('Y-m-d H:i:s')) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->saveFieldValues($item->id, $type['slug'], $request);
        $this->withFlash('success', 'آیتم به‌روزرسانی شد.');

        return $this->redirect('/admin/cpt/' . $type['slug']);
    }

    public function destroy(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('posts.delete', 'settings.manage')) {
            return $denied;
        }
        $type = $this->resolveType((string) $request->route('type', ''));
        if ($type !== null) {
            $this->posts->trash((int) $request->route('id', 0));
            $this->withFlash('success', 'آیتم به زباله‌دان منتقل شد.');
        }

        return $this->redirect('/admin/cpt/' . ($type['slug'] ?? ''));
    }

    /** @return array{slug: string, name: string, icon: string}|null */
    private function resolveType(string $slug): ?array
    {
        $slug = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $slug));
        $row = $this->db->table('post_types')->where('slug', $slug)->first();
        if ($row === null) {
            return null;
        }

        return ['slug' => (string) $row['slug'], 'name' => (string) $row['name'], 'icon' => (string) ($row['icon'] ?? 'post')];
    }

    /** Custom fields applicable to this CPT (empty location rules = global). */
    private function fieldsFor(string $type): array
    {
        $groups = $this->db->select('SELECT * FROM custom_field_groups WHERE is_active = 1 ORDER BY ordering, id');
        $fields = [];
        foreach ($groups as $group) {
            $rules = json_decode((string) ($group['location_rules'] ?? '[]'), true);
            if (!empty($rules) && !$this->rulesMatch((array) $rules, $type)) {
                continue;
            }
            $rows = $this->db->select('SELECT * FROM custom_fields WHERE group_id = :g ORDER BY ordering, id', ['g' => $group['id']]);
            foreach ($rows as $row) {
                $fields[] = [
                    'key' => (string) $row['key'],
                    'label' => (string) $row['label'],
                    'type' => (string) $row['type'],
                    'settings' => json_decode((string) ($row['settings'] ?? '{}'), true) ?: [],
                ];
            }
        }

        return $fields;
    }

    private function rulesMatch(array $rules, string $type): bool
    {
        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            if (($rule['param'] ?? '') === 'post_type' && ($rule['value'] ?? '') === $type) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function metaValues(int $postId): array
    {
        $rows = $this->db->select('SELECT `key`, `value` FROM post_meta WHERE post_id = :id', ['id' => $postId]);
        $out = [];
        foreach ($rows as $row) {
            $key = (string) $row['key'];
            if (!str_starts_with($key, 'field:')) {
                continue;
            }
            $out[substr($key, 6)] = (string) $row['value'];
        }

        return $out;
    }

    private function saveFieldValues(int $postId, string $type, Request $request): void
    {
        foreach ($this->fieldsFor($type) as $field) {
            $key = 'field:' . $field['key'];
            $raw = $request->input('fields', [])[$field['key']] ?? null;
            if (is_array($raw)) {
                $raw = json_encode($raw, JSON_UNESCAPED_UNICODE);
            }
            $value = is_scalar($raw) ? (string) $raw : '';
            $value = mb_substr($value, 0, 60000);

            $existing = $this->db->first(
                'SELECT id FROM post_meta WHERE post_id = :id AND `key` = :k',
                ['id' => $postId, 'k' => $key]
            );
            if ($existing === null) {
                if ($value !== '') {
                    $this->db->insert('post_meta', ['post_id' => $postId, 'key' => $key, 'value' => $value]);
                }
            } else {
                if ($value === '') {
                    $this->db->table('post_meta')->where('id', $existing['id'])->delete();
                } else {
                    $this->db->table('post_meta')->where('id', $existing['id'])->update(['value' => $value]);
                }
            }
        }
    }
}
