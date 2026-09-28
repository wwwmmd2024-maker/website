<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Repositories\MediaRepository;
use IRJalali\App\Repositories\OptionRepository;
use IRJalali\App\Repositories\PostRepository;
use IRJalali\App\Repositories\UserRepository;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\View\View;

final class DashboardController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly UserRepository $users,
        private readonly PostRepository $posts,
        private readonly MediaRepository $media,
        private readonly OptionRepository $options,
        private readonly Database $db,
        private readonly Logger $logger,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('dashboard.view')) {
            return $denied;
        }

        // Command-palette action: clear all caches.
        if ($request->input('clear_cache') !== null && $this->auth->can('settings.manage')) {
            try {
                Application::get()->make(\IRJalali\Core\Cache\CacheInterface::class)->clear();
                $this->logger->channel('admin')->info('Cache cleared', ['actor' => $this->auth->id()]);
                $this->withFlash('success', 'کش سیستم پاک شد.');
            } catch (\Throwable $e) {
                $this->withFlash('error', 'پاک‌سازی کش ناموفق بود: ' . $e->getMessage());
            }

            return $this->redirect('/admin');
        }

        // First login after install → setup wizard.
        if (!(bool) $this->options->get('setup_completed', false)
            && ($this->auth->can('settings.manage') || $this->auth->isSuperAdmin())) {
            return $this->redirect('/setup');
        }

        $recentLogs = $this->db->select(
            'SELECT * FROM security_logs ORDER BY id DESC LIMIT 8'
        );

        /**
         * Extension point: plugins contribute dashboard widgets.
         * Each entry: ['title' => string, 'html' => string, 'size' => 'half'|'full']
         */
        $widgets = Application::get()->make(\IRJalali\Core\Hooks\Hooks::class)
            ->applyFilters('dashboard.widgets', []);
        $widgets = array_filter(is_array($widgets) ? $widgets : [], fn ($w) => is_array($w) && isset($w['title'], $w['html']));

        return $this->render('admin.dashboard', [
            'widgets' => array_values($widgets),
            'stats' => [
                'users' => $this->users->count(),
                'pages' => $this->posts->countByType('page'),
                'posts' => $this->posts->countByType('post'),
                'media' => $this->media->count(),
            ],
            'mode' => $this->options->get('website_mode', 'general'),
            'websiteType' => $this->options->get('website_type', 'custom'),
            'version' => Application::get()->version(),
            'phpVersion' => PHP_VERSION,
            'recentLogs' => $recentLogs,
            'user' => $this->auth->user(),
        ]);
    }
}
