<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Repositories\PostRepository;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

/**
 * Shared CRUD for pages and posts (classic fields; builder via BuilderController).
 */
abstract class ContentController extends Controller
{
    abstract protected function type(): string;

    abstract protected function permBase(): string;

    abstract protected function baseUrl(): string;

    abstract protected function activeKey(): string;

    public function __construct(
        View $view,
        Auth $auth,
        protected readonly PostRepository $posts,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan($this->permBase() . '.view')) {
            return $denied;
        }
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;
        $search = mb_substr($request->str('q'), 0, 100);
        $status = in_array($request->str('status'), ['draft', 'published', 'scheduled'], true)
            ? $request->str('status') : '';

        return $this->render('admin.content.index', [
            'items' => $this->posts->adminList($this->type(), $perPage, ($page - 1) * $perPage, $search, $status),
            'total' => $this->posts->countAdmin($this->type(), $search, $status),
            'page' => $page,
            'perPage' => $perPage,
            'search' => $search,
            'status' => $status,
            'type' => $this->type(),
            'baseUrl' => $this->baseUrl(),
            'active' => $this->activeKey(),
            'user' => $this->auth->user(),
        ]);
    }

    public function create(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan($this->permBase() . '.create')) {
            return $denied;
        }

        return $this->render('admin.content.form', $this->formData(null, $request));
    }

    public function store(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan($this->permBase() . '.create')) {
            return $denied;
        }
        $data = $this->validated($request);
        if (!is_array($data)) {
            $this->withFlash('error', $data);

            return $this->redirect($this->baseUrl() . '/create');
        }
        $data['author_id'] = $this->auth->id();
        $post = $this->posts->create($data);
        $this->withFlash('success', 'ذخیره شد.');

        return $this->redirect($this->baseUrl() . '/' . $post->id . '/edit');
    }

    public function edit(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan($this->permBase() . '.edit')) {
            return $denied;
        }
        $id = (int) $request->route('id');
        $post = $this->posts->find($id);
        if ($post === null || $post->type !== $this->type()) {
            return $this->render('errors.404', [], 404);
        }

        return $this->render('admin.content.form', $this->formData($post, $request));
    }

    public function update(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan($this->permBase() . '.edit')) {
            return $denied;
        }
        $id = (int) $request->route('id');
        $post = $this->posts->find($id);
        if ($post === null || $post->type !== $this->type()) {
            return $this->render('errors.404', [], 404);
        }
        $data = $this->validated($request, $id);
        if (!is_array($data)) {
            $this->withFlash('error', $data);

            return $this->redirect($this->baseUrl() . '/' . $id . '/edit');
        }
        $this->posts->update($id, $data);
        $this->withFlash('success', 'ذخیره شد.');

        return $this->redirect($this->baseUrl() . '/' . $id . '/edit');
    }

    public function destroy(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan($this->permBase() . '.delete')) {
            return $denied;
        }
        $id = (int) $request->route('id');
        $post = $this->posts->find($id);
        if ($post === null || $post->type !== $this->type()) {
            return $this->render('errors.404', [], 404);
        }
        $this->posts->trash($id);
        $this->withFlash('success', 'به زباله‌دان منتقل شد.');

        return $this->redirect($this->baseUrl());
    }

    /** @return array<string, mixed>|string validated data or error message */
    private function validated(Request $request, ?int $ignoreId = null): array|string
    {
        $title = mb_substr(trim($request->str('title')), 0, 200);
        if ($title === '') {
            return 'عنوان الزامی است.';
        }
        $slug = $this->slugify($request->str('slug') !== '' ? $request->str('slug') : $title);
        if ($slug === '') {
            return 'نامک معتبر نیست.';
        }
        if ($this->posts->slugTaken($this->type(), $slug, $ignoreId)) {
            return 'این نامک قبلاً استفاده شده است.';
        }
        $status = $request->str('status');
        if (!in_array($status, ['draft', 'published', 'scheduled'], true)) {
            $status = 'draft';
        }
        if ($status === 'published' && !$this->auth->can($this->permBase() . '.publish')) {
            $status = 'draft';
        }
        $publishedAt = $request->str('published_at');
        if ($status === 'published' && $publishedAt === '') {
            $publishedAt = date('Y-m-d H:i:s');
        } elseif ($publishedAt !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/', $publishedAt)) {
            return 'تاریخ انتشار معتبر نیست.';
        }
        if ($status === 'scheduled' && $publishedAt === '') {
            return 'برای زمان‌بندی، تاریخ انتشار الزامی است.';
        }

        return [
            'post_type' => $this->type(),
            'title' => $title,
            'slug' => $slug,
            'excerpt' => mb_substr(trim($request->str('excerpt')), 0, 500) ?: null,
            'content' => $request->str('content') !== '' ? $request->str('content') : null,
            'status' => $status,
            'parent_id' => $request->str('parent_id') !== '' ? (int) $request->str('parent_id') : null,
            'menu_order' => (int) $request->str('menu_order', '0'),
            'featured_image' => mb_substr(trim($request->str('featured_image')), 0, 500) ?: null,
            'published_at' => $publishedAt !== '' ? str_replace('T', ' ', $publishedAt) : null,
        ];
    }

    /** @param object{id: int, title: string, slug: string, excerpt: ?string, content: ?string, status: string, publishedAt: ?string, template: ?string, featuredImage: ?string, row: array<string, mixed>}|null $post */
    private function formData(mixed $post, Request $request): array
    {
        $parents = $this->type() === 'page'
            ? $this->posts->adminList('page', 100, 0)
            : [];

        return [
            'post' => $post,
            'type' => $this->type(),
            'baseUrl' => $this->baseUrl(),
            'active' => $this->activeKey(),
            'parents' => $parents,
            'user' => $this->auth->user(),
            'canPublish' => $this->auth->can($this->permBase() . '.publish'),
        ];
    }

    private function slugify(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/[\s_]+/u', '-', $value);
        $value = (string) preg_replace('/[^\p{L}\p{N}\-]+/u', '', $value);
        $value = trim($value, '-');

        return mb_substr($value, 0, 180);
    }
}
