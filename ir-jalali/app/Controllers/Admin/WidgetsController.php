<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Widgets\WidgetRegistry;
use IRJalali\Core\View\View;

/**
 * Widget placement (Part 2 §14): assign registered widgets to sidebars,
 * order them and edit their schema-driven settings. Plugin widgets appear
 * here automatically once their plugin is active.
 */
final class WidgetsController extends Controller
{
    public const SIDEBARS = ['primary' => 'سایدبار اصلی', 'footer' => 'پابرگ'];

    public function __construct(
        View $view,
        Auth $auth,
        private readonly Database $db,
        private readonly WidgetRegistry $widgets,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }

        $instances = [];
        foreach ($this->db->table('widget_instances')->orderBy('sidebar')->orderBy('ordering')->orderBy('id')->get() as $row) {
            $data = json_decode((string) $row['data_json'], true) ?: [];
            $slug = (string) ($data['widget'] ?? '');
            $definition = $slug !== '' ? $this->widgets->get($slug) : null;
            $instances[] = [
                'id' => (int) $row['id'],
                'sidebar' => (string) $row['sidebar'],
                'ordering' => (int) $row['ordering'],
                'slug' => $slug,
                'title' => (string) ($data['title'] ?? ''),
                'widgetTitle' => $definition?->title ?? $slug,
                'available' => $definition !== null,
                'data' => $data,
                'schema' => $definition?->schema ?? [],
            ];
        }

        return $this->render('admin.widgets', [
            'available' => array_values($this->widgets->all()),
            'instances' => $instances,
            'sidebars' => self::SIDEBARS,
            'user' => $this->auth->user(),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $slug = (string) $request->str('widget');
        $definition = $this->widgets->get($slug);
        if ($definition === null) {
            $this->withFlash('error', 'ویجت انتخاب‌شده معتبر نیست.');

            return $this->redirect('/admin/widgets');
        }
        $sidebar = array_key_exists($request->str('sidebar'), self::SIDEBARS) ? $request->str('sidebar') : 'primary';

        $data = ['widget' => $slug, 'title' => mb_substr($request->str('title'), 0, 140)];
        foreach ($definition->schema as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }
            $data[$key] = mb_substr($request->str('w_' . $key, (string) ($definition->defaults[$key] ?? '')), 0, 500);
        }

        $maxOrder = (int) $this->db->value('SELECT COALESCE(MAX(ordering), 0) FROM widget_instances WHERE sidebar = :s', ['s' => $sidebar]);
        $now = date('Y-m-d H:i:s');
        $this->db->insert('widget_instances', [
            'uuid' => bin2hex(random_bytes(16)),
            'sidebar' => $sidebar,
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'ordering' => $maxOrder + 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->widgets->syncCatalog();
        $this->withFlash('success', 'ویجت اضافه شد.');

        return $this->redirect('/admin/widgets');
    }

    public function update(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $id = (int) $request->route('id', 0);
        $row = $this->db->first('SELECT * FROM widget_instances WHERE id = :id', ['id' => $id]);
        if ($row === null) {
            return $this->redirect('/admin/widgets');
        }
        $data = json_decode((string) $row['data_json'], true) ?: [];
        $slug = (string) ($data['widget'] ?? '');
        $definition = $slug !== '' ? $this->widgets->get($slug) : null;

        $data['title'] = mb_substr($request->str('title'), 0, 140);
        if ($definition !== null) {
            foreach ($definition->schema as $field) {
                $key = (string) ($field['key'] ?? '');
                if ($key !== '') {
                    $data[$key] = mb_substr($request->str('w_' . $key, (string) ($data[$key] ?? '')), 0, 500);
                }
            }
        }
        $this->db->table('widget_instances')->where('id', $id)->update([
            'data_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->withFlash('success', 'تنظیمات ویجت ذخیره شد.');

        return $this->redirect('/admin/widgets');
    }

    public function move(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $id = (int) $request->route('id', 0);
        $dir = $request->str('dir') === 'down' ? 1 : -1;
        $row = $this->db->first('SELECT * FROM widget_instances WHERE id = :id', ['id' => $id]);
        if ($row !== null) {
            $newOrder = max(0, (int) $row['ordering'] + $dir);
            $sibling = $this->db->first(
                'SELECT id FROM widget_instances WHERE sidebar = :s AND ordering = :o AND id != :id LIMIT 1',
                ['s' => $row['sidebar'], 'o' => $newOrder, 'id' => $id]
            );
            if ($sibling !== null) {
                $this->db->table('widget_instances')->where('id', $sibling['id'])->update(['ordering' => (int) $row['ordering']]);
            }
            $this->db->table('widget_instances')->where('id', $id)->update(['ordering' => $newOrder]);
        }

        return $this->redirect('/admin/widgets');
    }

    public function destroy(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $this->db->table('widget_instances')->where('id', (int) $request->route('id', 0))->delete();
        $this->withFlash('success', 'ویجت حذف شد.');

        return $this->redirect('/admin/widgets');
    }
}
