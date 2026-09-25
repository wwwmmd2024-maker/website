<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Repositories\MenuRepository;
use IRJalali\App\Repositories\PostRepository;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

final class MenuController extends Controller
{
    public const LOCATIONS = ['primary', 'footer', 'mobile'];

    public function __construct(
        View $view,
        Auth $auth,
        private readonly MenuRepository $menus,
        private readonly PostRepository $posts,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(): Response
    {
        if ($denied = $this->denyUnlessCan('menus.manage')) {
            return $denied;
        }

        return $this->render('admin.menus.index', [
            'menus' => $this->menus->listMenus(),
            'locations' => self::LOCATIONS,
            'active' => 'menus',
            'user' => $this->auth->user(),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('menus.manage')) {
            return $denied;
        }
        $name = mb_substr(trim($request->str('name')), 0, 120);
        if ($name === '') {
            $this->withFlash('error', 'نام فهرست الزامی است.');

            return $this->redirect('/admin/menus');
        }
        $location = $request->str('location');
        if (!in_array($location, self::LOCATIONS, true)) {
            $location = 'primary';
        }
        $slug = mb_strtolower((string) preg_replace('/[\s_]+/u', '-', $name));
        $slug = mb_substr(trim((string) preg_replace('/[^\p{L}\p{N}\-]+/u', '', $slug), '-'), 0, 120);
        if ($slug === '') {
            $slug = 'menu-' . time();
        }
        $id = $this->menus->createMenu($slug, $name, $location);
        $this->withFlash('success', 'فهرست ساخته شد.');

        return $this->redirect('/admin/menus/' . $id . '/edit');
    }

    public function edit(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('menus.manage')) {
            return $denied;
        }
        $id = (int) $request->route('id');
        $menu = $this->menus->findMenu($id);
        if ($menu === null) {
            return $this->render('errors.404', [], 404);
        }

        return $this->render('admin.menus.edit', [
            'menu' => $menu,
            'items' => $this->menus->flatItems($id),
            'locations' => self::LOCATIONS,
            'pages' => $this->posts->adminList('page', 100, 0, '', 'published'),
            'posts' => $this->posts->adminList('post', 100, 0, '', 'published'),
            'active' => 'menus',
            'user' => $this->auth->user(),
        ]);
    }

    public function update(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('menus.manage')) {
            return $denied;
        }
        $id = (int) $request->route('id');
        $menu = $this->menus->findMenu($id);
        if ($menu === null) {
            return $this->render('errors.404', [], 404);
        }
        $name = mb_substr(trim($request->str('name')), 0, 120);
        if ($name === '') {
            $this->withFlash('error', 'نام فهرست الزامی است.');

            return $this->redirect('/admin/menus/' . $id . '/edit');
        }
        $location = $request->str('location');
        if (!in_array($location, self::LOCATIONS, true)) {
            $location = (string) ($menu['location'] ?? 'primary');
        }
        $this->menus->updateMenu($id, $name, $location);
        $this->withFlash('success', 'ذخیره شد.');

        return $this->redirect('/admin/menus/' . $id . '/edit');
    }

    public function destroy(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('menus.manage')) {
            return $denied;
        }
        $id = (int) $request->route('id');
        $this->menus->deleteMenu($id);
        $this->withFlash('success', 'فهرست حذف شد.');

        return $this->redirect('/admin/menus');
    }

    public function storeItem(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('menus.manage')) {
            return $denied;
        }
        $menuId = (int) $request->route('id');
        if ($this->menus->findMenu($menuId) === null) {
            return $this->render('errors.404', [], 404);
        }
        $item = $this->validatedItem($request);
        if (!is_array($item)) {
            $this->withFlash('error', $item);

            return $this->redirect('/admin/menus/' . $menuId . '/edit');
        }
        $item['ordering'] = $this->menus->nextOrdering($menuId);
        $this->menus->addItem($menuId, $item);
        $this->withFlash('success', 'آیتم افزوده شد.');

        return $this->redirect('/admin/menus/' . $menuId . '/edit');
    }

    public function updateItem(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('menus.manage')) {
            return $denied;
        }
        $menuId = (int) $request->route('id');
        $itemId = (int) $request->route('item');
        $existing = $this->menus->findItem($itemId);
        if ($existing === null || (int) $existing['menu_id'] !== $menuId) {
            return $this->render('errors.404', [], 404);
        }
        $action = $request->str('move');
        if ($action === 'up' || $action === 'down') {
            $this->moveItem($menuId, $existing, $action === 'up' ? -1 : 1);

            return $this->redirect('/admin/menus/' . $menuId . '/edit');
        }
        $item = $this->validatedItem($request, $itemId);
        if (!is_array($item)) {
            $this->withFlash('error', $item);

            return $this->redirect('/admin/menus/' . $menuId . '/edit');
        }
        $item['ordering'] = (int) $existing['ordering'];
        $this->menus->updateItem($itemId, $item);
        $this->withFlash('success', 'ذخیره شد.');

        return $this->redirect('/admin/menus/' . $menuId . '/edit');
    }

    public function destroyItem(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('menus.manage')) {
            return $denied;
        }
        $menuId = (int) $request->route('id');
        $itemId = (int) $request->route('item');
        $existing = $this->menus->findItem($itemId);
        if ($existing === null || (int) $existing['menu_id'] !== $menuId) {
            return $this->render('errors.404', [], 404);
        }
        $this->menus->deleteItem($itemId);
        $this->withFlash('success', 'آیتم حذف شد.');

        return $this->redirect('/admin/menus/' . $menuId . '/edit');
    }

    /** @return array<string, mixed>|string */
    private function validatedItem(Request $request, ?int $ignoreId = null): array|string
    {
        $title = mb_substr(trim($request->str('title')), 0, 150);
        if ($title === '') {
            return 'عنوان آیتم الزامی است.';
        }
        $type = $request->str('type');
        if (!in_array($type, ['custom', 'page', 'post'], true)) {
            $type = 'custom';
        }
        $url = null;
        $referenceType = null;
        $referenceId = null;
        if ($type === 'custom') {
            $url = mb_substr(trim($request->str('url')), 0, 500);
            if ($url === '' || (!str_starts_with($url, '/') && !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && $url !== '#')) {
                return 'نشانی معتبر نیست (باید با / یا http(s) شروع شود).';
            }
        } else {
            $referenceId = (int) $request->str('reference_id');
            $post = $this->posts->find($referenceId);
            if ($post === null || $post->type !== $type) {
                return 'مرجع انتخاب‌شده معتبر نیست.';
            }
            $referenceType = $type;
            $url = '/' . $post->slug;
        }
        $parentId = $request->str('parent_id') !== '' ? (int) $request->str('parent_id') : null;
        if ($parentId !== null) {
            if ($ignoreId !== null && $parentId === $ignoreId) {
                return 'والد نمی‌تواند خود آیتم باشد.';
            }
            $parent = $this->menus->findItem($parentId);
            if ($parent === null) {
                return 'والد انتخاب‌شده معتبر نیست.';
            }
        }
        $target = $request->str('target') === '_blank' ? '_blank' : '_self';

        return [
            'parent_id' => $parentId,
            'title' => $title,
            'type' => $type,
            'url' => $url,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'target' => $target,
            'css_class' => mb_substr(trim($request->str('css_class')), 0, 120) ?: null,
        ];
    }

    /** @param array<string, mixed> $item */
    private function moveItem(int $menuId, array $item, int $direction): void
    {
        $items = $this->menus->flatItems($menuId);
        $index = null;
        foreach ($items as $i => $row) {
            if ((int) $row['id'] === (int) $item['id']) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return;
        }
        $swap = $index + $direction;
        if (!isset($items[$swap])) {
            return;
        }
        $this->menus->updateItem((int) $item['id'], ['ordering' => (int) $items[$swap]['ordering']]);
        $this->menus->updateItem((int) $items[$swap]['id'], ['ordering' => (int) $item['ordering']]);
    }
}
