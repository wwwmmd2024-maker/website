<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers;

use IRJalali\App\Repositories\OptionRepository;
use IRJalali\App\Services\SetupService;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

final class SetupController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly SetupService $setup,
        private readonly OptionRepository $options,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ((bool) $this->options->get('setup_completed', false)) {
            return $this->redirect('/admin');
        }
        if (!$this->auth->can('settings.manage') && !$this->auth->isSuperAdmin()) {
            return $this->redirect('/admin');
        }

        return $this->render('setup.index', [
            'types' => SetupService::websiteTypes(),
        ]);
    }

    public function apply(Request $request): Response
    {
        if ((bool) $this->options->get('setup_completed', false)) {
            return $this->redirect('/admin');
        }
        if (!$this->auth->can('settings.manage') && !$this->auth->isSuperAdmin()) {
            return Response::text('Forbidden', 403);
        }
        $type = $request->str('website_type', 'custom');
        if (!SetupService::isValidType($type)) {
            $type = 'custom';
        }

        try {
            $result = $this->setup->apply($type);
        } catch (\Throwable $e) {
            $this->withFlash('error', 'خطا در راه‌اندازی: ' . $e->getMessage());

            return $this->redirect('/setup');
        }

        $this->withFlash('success', "راه‌اندازی اولیه انجام شد ({$result['pages']} برگه ساخته شد).");

        return $this->redirect('/admin');
    }
}
