<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Repositories\TemplateRepository;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

/**
 * Template library (meta CRUD; design happens in the builder).
 * Uses pages.* permissions — no templates.* permission exists by design.
 */
final class TemplateController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly TemplateRepository $templates,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('pages.view')) {
            return $denied;
        }
        $this->templates->ensureSiteParts();
        $type = $request->str('type');
        if (!in_array($type, TemplateRepository::TYPES, true)) {
            $type = '';
        }

        return $this->render('admin.templates.index', [
            'items' => $this->templates->list($type !== '' ? $type : null),
            'type' => $type,
            'types' => TemplateRepository::TYPES,
            'active' => 'templates',
            'user' => $this->auth->user(),
        ]);
    }

    public function create(): Response
    {
        if ($denied = $this->denyUnlessCan('pages.create')) {
            return $denied;
        }

        return $this->render('admin.templates.form', [
            'template' => null,
            'types' => TemplateRepository::TYPES,
            'active' => 'templates',
            'user' => $this->auth->user(),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('pages.create')) {
            return $denied;
        }
        $data = $this->validated($request);
        if (!is_array($data)) {
            $this->withFlash('error', $data);

            return $this->redirect('/admin/templates/create');
        }
        $id = $this->templates->create($data);
        $this->withFlash('success', 'قالب ساخته شد.');

        return $this->redirect('/admin/builder/template/' . $id);
    }

    public function edit(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('pages.edit')) {
            return $denied;
        }
        $id = (int) $request->route('id');
        $template = $this->templates->find($id);
        if ($template === null) {
            return $this->render('errors.404', [], 404);
        }

        return $this->render('admin.templates.form', [
            'template' => $template,
            'types' => TemplateRepository::TYPES,
            'active' => 'templates',
            'user' => $this->auth->user(),
        ]);
    }

    public function update(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('pages.edit')) {
            return $denied;
        }
        $id = (int) $request->route('id');
        $template = $this->templates->find($id);
        if ($template === null) {
            return $this->render('errors.404', [], 404);
        }
        $data = $this->validated($request, $id);
        if (!is_array($data)) {
            $this->withFlash('error', $data);

            return $this->redirect('/admin/templates/' . $id . '/edit');
        }
        $this->templates->update($id, $data);
        $this->withFlash('success', 'ذخیره شد.');

        return $this->redirect('/admin/templates/' . $id . '/edit');
    }

    public function destroy(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('pages.delete')) {
            return $denied;
        }
        $id = (int) $request->route('id');
        $template = $this->templates->find($id);
        if ($template === null) {
            return $this->render('errors.404', [], 404);
        }
        $this->templates->delete($id);
        $this->withFlash('success', 'قالب حذف شد.');

        return $this->redirect('/admin/templates');
    }

    /** @return array<string, mixed>|string */
    private function validated(Request $request, ?int $ignoreId = null): array|string
    {
        $name = mb_substr(trim($request->str('name')), 0, 150);
        if ($name === '') {
            return 'نام قالب الزامی است.';
        }
        $slug = mb_strtolower(trim($request->str('slug') !== '' ? $request->str('slug') : $name));
        $slug = (string) preg_replace('/[\s_]+/u', '-', $slug);
        $slug = (string) preg_replace('/[^\p{L}\p{N}\-]+/u', '', $slug);
        $slug = mb_substr(trim($slug, '-'), 0, 120);
        if ($slug === '') {
            return 'نامک معتبر نیست.';
        }
        if ($this->templates->slugTaken($slug, $ignoreId)) {
            return 'این نامک قبلاً استفاده شده است.';
        }
        $type = $request->str('type');
        if (!in_array($type, TemplateRepository::TYPES, true)) {
            $type = 'page';
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'is_default' => $request->str('is_default') === '1',
        ];
    }
}
