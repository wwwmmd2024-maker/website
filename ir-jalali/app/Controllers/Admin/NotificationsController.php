<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Services\NotificationService;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

/** Notification Center (Part 3 §16). */
final class NotificationsController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly NotificationService $notifications,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('dashboard.view')) {
            return $denied;
        }

        return $this->render('admin.notifications', [
            'items' => $this->notifications->recent(100),
            'unread' => $this->notifications->count(true),
            'user' => $this->auth->user(),
        ]);
    }

    public function markRead(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('dashboard.view')) {
            return $denied;
        }
        $this->notifications->markRead((int) $request->route('id', 0));

        return $this->redirect('/admin/notifications');
    }

    public function markAllRead(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('dashboard.view')) {
            return $denied;
        }
        $this->notifications->markAllRead();

        return $this->redirect('/admin/notifications');
    }

    public function delete(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $this->notifications->delete((int) $request->route('id', 0));

        return $this->redirect('/admin/notifications');
    }

    public function clearRead(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $this->notifications->clearRead();

        return $this->redirect('/admin/notifications');
    }
}
