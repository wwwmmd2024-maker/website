<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Services\WebsiteModeService;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

/** Website Mode System (Part 1 §6 / Part 2 §46). */
final class ModesController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly WebsiteModeService $modes,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }

        $cards = [];
        foreach (WebsiteModeService::MODES as $slug => $row) {
            $cards[] = [
                'slug' => $slug,
                'label' => $row[0],
                'description' => $row[1],
                'plugins' => $row[2],
            ];
        }

        return $this->render('admin.modes', [
            'cards' => $cards,
            'current' => $this->modes->current(),
            'pluginStatus' => $this->modes->pluginStatus(),
            'user' => $this->auth->user(),
        ]);
    }

    public function apply(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('settings.manage')) {
            return $denied;
        }
        $mode = (string) $request->route('mode', '');
        $withPlugins = $request->input('plugins') !== null;
        try {
            $results = $this->modes->apply($mode, $withPlugins, $this->auth->id());
            $problems = array_filter($results, fn (array $r): bool => !$r['ok']);
            if ($problems === []) {
                $this->withFlash('success', 'حالت وب‌سایت اعمال شد.');
            } else {
                $messages = implode(' ', array_map(fn (array $r): string => $r['slug'] . ': ' . $r['message'], array_values($problems)));
                $this->withFlash('error', 'حالت ذخیره شد اما برخی افزونه‌ها فعال نشدند: ' . $messages);
            }
        } catch (\Throwable $e) {
            $this->withFlash('error', $e->getMessage());
        }

        return $this->redirect('/admin/modes');
    }
}
