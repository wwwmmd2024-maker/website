<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Services\AuditService;
use IRJalali\App\Services\SettingsService;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Translation\Translator;
use IRJalali\Core\Validation\Validator;
use IRJalali\Core\View\View;

final class SettingsController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly SettingsService $settings,
        private readonly AuditService $audit,
        private readonly Translator $t,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }

        return $this->render('admin.settings', [
            'settings' => $this->settings->all(),
            'modes' => SettingsService::websiteModes(),
            'user' => $this->auth->user(),
        ]);
    }

    public function update(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }

        $data = $request->only('site_title', 'tagline', 'language', 'timezone', 'website_mode');
        $validator = Validator::make($data, [
            'site_title' => 'required|max:150',
            'language' => 'required|in:fa_IR,en_US',
            'timezone' => 'required|max:40',
            'website_mode' => 'required|max:30',
        ]);
        if ($validator->fails()) {
            $this->withFlash('error', 'اطلاعات فرم نامعتبر است.');

            return $this->redirect('/admin/settings');
        }

        try {
            new \DateTimeZone($data['timezone']);
        } catch (\Throwable) {
            $this->withFlash('error', 'منطقه زمانی نامعتبر است.');

            return $this->redirect('/admin/settings');
        }

        $before = $this->settings->all();
        $this->settings->update($data);
        $this->audit->audit($this->auth->id(), 'settings.update', 'settings', null, $before, $data);
        $this->withFlash('success', $this->t->get('settings.saved'));

        return $this->redirect('/admin/settings');
    }
}
