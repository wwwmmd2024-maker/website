<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers;

use IRJalali\App\Services\InstallerService;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Validation\Validator;
use IRJalali\Core\View\View;

final class InstallController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly InstallerService $installer,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        $step = $request->str('step', 'requirements');
        if (!in_array($step, ['requirements', 'database', 'admin', 'site'], true)) {
            $step = 'requirements';
        }

        // Persist wizard state in session between steps.
        if ($step === 'requirements') {
            $req = $this->installer->requirements();

            return $this->render('installer.requirements', [
                'checks' => $req['checks'],
                'ok' => $req['ok'],
                'step' => $step,
            ]);
        }

        return $this->render('installer.form', [
            'step' => $step,
            'stored' => $_SESSION['irj_install'] ?? [],
        ]);
    }

    public function store(Request $request): Response
    {
        $step = $request->str('step', 'database');
        // Merge only submitted fields — never wipe previous steps with nulls.
        $incoming = array_filter($request->only(
            'db_host',
            'db_port',
            'db_name',
            'db_user',
            'db_pass',
            'admin_username',
            'admin_email',
            'admin_password',
            'admin_password_confirm',
            'site_title',
            'site_tagline',
            'site_language',
            'site_timezone',
            'site_url'
        ), static fn($value) => $value !== null);
        $_SESSION['irj_install'] = array_merge($_SESSION['irj_install'] ?? [], $incoming);

        if ($step === 'database') {
            $stored = $_SESSION['irj_install'];
            $validator = Validator::make($stored, [
                'db_host' => 'required|max:120',
                'db_port' => 'required|integer',
                'db_name' => 'required|max:64',
                'db_user' => 'required|max:64',
            ]);
            if ($validator->fails()) {
                $this->withFlash('error', 'اطلاعات دیتابیس ناقص است.');

                return $this->redirect('/install?step=database');
            }
            if (!$this->installer->testConnection([
                'host' => $stored['db_host'],
                'port' => $stored['db_port'],
                'database' => $stored['db_name'],
                'username' => $stored['db_user'],
                'password' => $stored['db_pass'] ?? '',
            ])) {
                $this->withFlash('error', 'اتصال به دیتابیس برقرار نشد. مشخصات را بررسی کنید.');

                return $this->redirect('/install?step=database');
            }
            $this->withFlash('success', 'اتصال به دیتابیس با موفقیت برقرار شد.');

            return $this->redirect('/install?step=admin');
        }

        if ($step === 'admin') {
            $stored = $_SESSION['irj_install'];
            $validator = Validator::make($stored, [
                'admin_username' => 'required|min:3|max:60',
                'admin_email' => 'required|email|max:191',
                'admin_password' => 'required|min:8|max:72',
            ]);
            if ($validator->fails() || ($stored['admin_password'] ?? '') !== ($stored['admin_password_confirm'] ?? '')) {
                $this->withFlash('error', 'اطلاعات مدیر نامعتبر است (گذرواژه حداقل ۸ کاراکتر و تکرار آن یکسان باشد).');

                return $this->redirect('/install?step=admin');
            }

            return $this->redirect('/install?step=site');
        }

        if ($step === 'site') {
            $stored = $_SESSION['irj_install'] ?? [];
            foreach (['db_host', 'db_name', 'db_user', 'admin_username', 'admin_email', 'admin_password'] as $required) {
                if (empty($stored[$required])) {
                    $this->withFlash('error', 'اطلاعات مراحل قبلی یافت نشد. لطفاً از ابتدا شروع کنید.');

                    return $this->redirect('/install?step=database');
                }
            }
            $validator = Validator::make($stored, [
                'site_title' => 'required|max:150',
                'site_language' => 'required|in:fa_IR,en_US',
                'site_timezone' => 'required|max:40',
            ]);
            if ($validator->fails()) {
                $this->withFlash('error', 'اطلاعات سایت نامعتبر است.');

                return $this->redirect('/install?step=site');
            }

            try {
                $this->installer->install(
                    [
                        'host' => $stored['db_host'],
                        'port' => $stored['db_port'],
                        'database' => $stored['db_name'],
                        'username' => $stored['db_user'],
                        'password' => $stored['db_pass'] ?? '',
                    ],
                    [
                        'username' => $stored['admin_username'],
                        'email' => $stored['admin_email'],
                        'password' => $stored['admin_password'],
                    ],
                    [
                        'title' => $stored['site_title'],
                        'tagline' => $stored['site_tagline'] ?? '',
                        'language' => $stored['site_language'],
                        'timezone' => $stored['site_timezone'],
                        'url' => $stored['site_url'] ?? '',
                    ]
                );
            } catch (\Throwable $e) {
                $this->withFlash('error', 'نصب ناموفق بود: ' . $e->getMessage());

                return $this->redirect('/install?step=site');
            }

            unset($_SESSION['irj_install']);

            return $this->render('installer.finish', []);
        }

        return $this->redirect('/install');
    }
}
