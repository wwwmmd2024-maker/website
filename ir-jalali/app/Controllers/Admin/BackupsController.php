<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Services\NotificationService;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Updates\SiteBackupService;
use IRJalali\Core\View\View;

/**
 * Backup Manager (Part 3 §21–22): create / download / restore / delete /
 * schedule full-site backups. Restore performs integrity, version and file
 * checks and the update pipeline always snapshots before applying changes.
 */
final class BackupsController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly SiteBackupService $backups,
        private readonly NotificationService $notifications,
        private readonly Logger $logger,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }

        return $this->render('admin.backups', [
            'backups' => $this->backups->all(),
            'scheduled' => $this->scheduleStatus(),
            'user' => $this->auth->user(),
        ]);
    }

    public function create(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        try {
            $path = $this->backups->create('manual');
            $this->withFlash('success', 'پشتیبان کامل سایت ساخته شد: ' . basename($path));
            $this->logger->channel('backups')->info('Backup created', ['path' => $path, 'actor' => $this->auth->id()]);
        } catch (\Throwable $e) {
            $this->withFlash('error', 'پشتیبان‌گیری ناموفق بود: ' . $e->getMessage());
        }

        return $this->redirect('/admin/backups');
    }

    public function download(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $path = $this->backups->path((string) $request->route('name', ''));
        if ($path === null) {
            return Response::text('Not found.', 404);
        }
        $content = (string) file_get_contents($path);

        return (new Response($content, 200, ['Content-Type' => 'application/zip']))
            ->withHeader('Content-Disposition', 'attachment; filename="' . basename($path) . '"')
            ->withHeader('Content-Length', (string) strlen($content));
    }

    public function restore(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $name = (string) $request->route('name', '');
        try {
            // Snapshot current state so a failed restore is itself recoverable.
            $pre = $this->backups->create('pre-restore');
            $result = $this->backups->restore($name);
            $preName = $pre !== '' ? basename($pre) : '-';
            $this->withFlash('success', "بازیابی انجام شد ({$result['tables']} جدول، {$result['files']} فایل). وضعیت قبلی در «{$preName}» ذخیره شد.");
            $this->notifications->pushToAdmins('system.error', 'بازیابی پشتیبان', "پشتیبان {$name} بازیابی شد.", '/admin/backups');
            $this->logger->channel('backups')->warning('Backup restored', ['name' => $name, 'actor' => $this->auth->id()]);
        } catch (\Throwable $e) {
            $this->withFlash('error', 'بازیابی ناموفق بود: ' . $e->getMessage());
        }

        return $this->redirect('/admin/backups');
    }

    public function delete(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $ok = $this->backups->delete((string) $request->route('name', ''));
        $this->withFlash($ok ? 'success' : 'error', $ok ? 'پشتیبان حذف شد.' : 'پشتیبان یافت نشد.');

        return $this->redirect('/admin/backups');
    }

    public function toggleSchedule(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        /** @var \IRJalali\Core\Scheduler\Scheduler $scheduler */
        $scheduler = \IRJalali\Core\Kernel\Application::get()->make(\IRJalali\Core\Scheduler\Scheduler::class);
        $enable = $request->input('enable') !== null;
        if ($enable) {
            $scheduler->ensureTask('core.site_backup', 'پشتیبان‌گیری خودکار سایت', 'daily');
            $this->withFlash('success', 'پشتیبان‌گیری خودکار روزانه فعال شد (نیازمند کرون).');
        } else {
            try {
                \IRJalali\Core\Kernel\Application::get()->make(\IRJalali\Core\Database\Database::class)
                    ->table('scheduled_tasks')->where('hook', 'core.site_backup')->delete();
                $this->withFlash('success', 'پشتیبان‌گیری خودکار غیرفعال شد.');
            } catch (\Throwable $e) {
                $this->withFlash('error', $e->getMessage());
            }
        }

        return $this->redirect('/admin/backups');
    }

    private function scheduleStatus(): bool
    {
        try {
            $row = \IRJalali\Core\Kernel\Application::get()->make(\IRJalali\Core\Database\Database::class)
                ->table('scheduled_tasks')->where('hook', 'core.site_backup')->first();

            return $row !== null;
        } catch (\Throwable) {
            return false;
        }
    }
}
