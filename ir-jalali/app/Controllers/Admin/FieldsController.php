<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

/**
 * Custom Field Builder (Part 1 §11): field groups + fields with location
 * rules, editable without code. All 30 field types from the spec are
 * supported; complex values are stored as JSON in post_meta.
 */
final class FieldsController extends Controller
{
    /** Every field type from Master Prompt Part 1 §11. */
    public const FIELD_TYPES = [
        'text', 'textarea', 'richtext', 'number', 'currency', 'email', 'phone', 'url',
        'password', 'date', 'time', 'datetime', 'image', 'gallery', 'file',
        'select', 'multiselect', 'checkbox', 'radio', 'toggle', 'color',
        'repeater', 'group', 'relationship', 'post_selector', 'user_selector',
        'taxonomy_selector', 'map', 'code', 'json',
    ];

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

        $groups = $this->db->select('SELECT * FROM custom_field_groups ORDER BY ordering, id');
        foreach ($groups as &$group) {
            $group['fields'] = $this->db->select('SELECT * FROM custom_fields WHERE group_id = :g ORDER BY ordering, id', ['g' => $group['id']]);
        }
        unset($group);

        return $this->render('admin.fields', [
            'groups' => $groups,
            'types' => self::FIELD_TYPES,
            'postTypes' => $this->db->select('SELECT slug, name FROM post_types ORDER BY slug'),
            'user' => $this->auth->user(),
        ]);
    }

    public function storeGroup(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $title = mb_substr(trim($request->str('title')), 0, 150);
        if ($title === '') {
            $this->withFlash('error', 'عنوان گروه فیلد الزامی است.');

            return $this->redirect('/admin/fields');
        }
        $postType = trim($request->str('post_type'));
        $rules = $postType !== '' && $postType !== '*'
            ? [['param' => 'post_type', 'value' => $postType]]
            : [];
        $now = date('Y-m-d H:i:s');
        $this->db->insert('custom_field_groups', [
            'title' => $title,
            'location_rules' => json_encode($rules, JSON_UNESCAPED_UNICODE),
            'ordering' => 0,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->withFlash('success', 'گروه فیلد ساخته شد.');

        return $this->redirect('/admin/fields');
    }

    public function deleteGroup(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $id = (int) $request->route('id', 0);
        $this->db->delete('custom_fields', 'group_id = :g', ['g' => $id]);
        $this->db->table('custom_field_groups')->where('id', $id)->delete();
        $this->withFlash('success', 'گروه فیلد حذف شد (مقادیر ذخیره‌شده در محتوا حفظ می‌شوند).');

        return $this->redirect('/admin/fields');
    }

    public function storeField(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $groupId = (int) $request->route('id', 0);
        if ($this->db->first('SELECT id FROM custom_field_groups WHERE id = :id', ['id' => $groupId]) === null) {
            return $this->redirect('/admin/fields');
        }
        $label = mb_substr(trim($request->str('label')), 0, 140);
        $key = strtolower(preg_replace('/[^a-z0-9_\-]/i', '', $request->str('key')));
        $type = in_array($request->str('type'), self::FIELD_TYPES, true) ? $request->str('type') : 'text';
        if ($label === '' || $key === '') {
            $this->withFlash('error', 'برچسب و کلید فیلد الزامی است.');

            return $this->redirect('/admin/fields');
        }
        if ($this->db->first('SELECT id FROM custom_fields WHERE group_id = :g AND `key` = :k', ['g' => $groupId, 'k' => $key]) !== null) {
            $this->withFlash('error', 'این کلید فیلد در این گروه تکراری است.');

            return $this->redirect('/admin/fields');
        }

        $settings = [];
        $choices = trim($request->str('choices'));
        if ($choices !== '') {
            $settings['choices'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|,/', $choices) ?: [])));
        }
        if ($request->str('default') !== '') {
            $settings['default'] = $request->str('default');
        }
        if ($request->input('required') !== null) {
            $settings['required'] = true;
        }

        $now = date('Y-m-d H:i:s');
        $maxOrder = (int) $this->db->value('SELECT COALESCE(MAX(ordering), 0) FROM custom_fields WHERE group_id = :g', ['g' => $groupId]);
        $this->db->insert('custom_fields', [
            'group_id' => $groupId,
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            'ordering' => $maxOrder + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->withFlash('success', 'فیلد اضافه شد.');

        return $this->redirect('/admin/fields');
    }

    public function deleteField(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $this->db->table('custom_fields')->where('id', (int) $request->route('field', 0))->delete();
        $this->withFlash('success', 'فیلد حذف شد.');

        return $this->redirect('/admin/fields');
    }
}
