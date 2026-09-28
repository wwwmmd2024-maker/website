<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Plugins\PluginManager;
use IRJalali\Core\View\View;

/**
 * Dispatches plugin-registered admin pages (GET renders, POST submits).
 */
final class PluginPageController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly PluginManager $plugins,
        private readonly Logger $logger,
    ) {
        parent::__construct($view, $auth);
    }

    public function show(Request $request): Response
    {
        $slug = (string) $request->route('slug', '');
        $handler = $this->plugins->adminPage($slug);
        if ($handler === null) {
            return Response::html($this->view->render('errors.404', ['flash' => $this->pullFlash()]), 404);
        }
        $permission = $this->plugins->adminPagePermission($slug) ?? 'dashboard.view';
        if (($denied = $this->denyUnlessCan($permission)) !== null) {
            return $denied;
        }

        try {
            $result = $handler($request);
        } catch (\Throwable $e) {
            $this->logger->channel('plugins')->error('Plugin admin page failed', ['page' => $slug, 'error' => $e->getMessage()]);

            return Response::html($this->view->render('errors.500', ['flash' => null]), 500);
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }

        return $this->render('admin.plugin-page', [
            'title' => $this->pageTitle($slug),
            'active' => 'plugins',
            'content' => (string) $result,
        ]);
    }

    private function pageTitle(string $slug): string
    {
        foreach ($this->plugins->adminPages() as $page) {
            if ($page['slug'] === $slug) {
                return $page['title'];
            }
        }

        return 'افزونه';
    }
}
