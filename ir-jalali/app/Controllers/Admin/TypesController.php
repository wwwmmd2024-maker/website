<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Validation\Validator;
use IRJalali\Core\View\View;

/**
 * Custom Post Type Builder (Part 1 §10): admins create CPTs without code.
 * Supports, taxonomies, templates, archive/single flags are stored per type.
 */
final class TypesController extends Controller
{
    public const SUPPORTS = ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'seo', 'fields'];

    public function __construct(
        View $view,
        Auth $auth,
        private readonly Database $db,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }

        return $this->render('admin.types', [
            'types' => $this->db->table('post_types')->orderBy('slug')->get(),
            'supports' => self::SUPPORTS,
            'user' => $this->auth->user(),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $data = $request->only('name', 'slug', 'icon');
        $validator = Validator::make($data, ['name' => 'required|max:90', 'slug' => 'required|max:40']);
        if ($validator->fails()) {
            $this->withFlash('error', 'نام و شناسه نوع نوشته الزامی است.');

            return $this->redirect('/admin/types');
        }
        $slug = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $data['slug']));
        if ($slug === '' || !preg_match('/^[a-z0-9_\-]{2,40}$/', $slug)) {
            $this->withFlash('error', 'شناسه نامعتبر است (حروف انگلیسی، عدد، - و _).');

            return $this->redirect('/admin/types');
        }
        if ($this->db->table('post_types')->where('slug', $slug)->first() !== null) {
            $this->withFlash('error', 'این شناسه از قبل وجود دارد.');

            return $this->redirect('/admin/types');
        }

        $supports = array_values(array_intersect((array) $request->input('supports', []), self::SUPPORTS));
        $settings = [
            'archive' => $request->input('archive') !== null,
            'single' => $request->input('single') !== null,
            'rest_api' => $request->input('rest_api') !== null,
            'taxonomies' => array_values(array_filter(array_map('trim', explode(',', $request->str('taxonomies', ''))))),
            'templates' => array_values(array_filter(array_map('trim', explode(',', $request->str('templates', ''))))),
            'source' => 'admin',
        ];

        $now = date('Y-m-d H:i:s');
        $this->db->insert('post_types', [
            'slug' => $slug,
            'name' => mb_substr((string) $data['name'], 0, 90),
            'icon' => mb_substr($request->str('icon', 'post'), 0, 50),
            'supports' => json_encode($supports, JSON_UNESCAPED_UNICODE),
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'is_system' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->withFlash('success', 'نوع نوشته ساخته شد.');

        return $this->redirect('/admin/types');
    }

    public function destroy(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $id = (int) $request->route('id', 0);
        $row = $this->db->first('SELECT slug, is_system FROM post_types WHERE id = :id', ['id' => $id]);
        if ($row === null) {
            return $this->redirect('/admin/types');
        }
        if ((int) $row['is_system'] === 1) {
            $this->withFlash('error', 'انواع سیستمی قابل حذف نیستند.');

            return $this->redirect('/admin/types');
        }
        // Never delete content — just remove the type definition.
        $this->db->table('post_types')->where('id', $id)->delete();
        $this->withFlash('success', 'نوع نوشته حذف شد (محتوای آن حفظ می‌شود).');

        return $this->redirect('/admin/types');
    }
}
