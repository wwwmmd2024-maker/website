<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers;

use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

/**
 * Base controller: view rendering, flash messages, permission gates.
 */
abstract class Controller
{
    public function __construct(
        protected readonly View $view,
        protected readonly Auth $auth,
    ) {
    }

    protected function render(string $view, array $data = [], int $status = 200): Response
    {
        $data['flash'] = $this->pullFlash();

        return Response::html($this->view->render($view, $data), $status);
    }

    protected function redirect(string $url): Response
    {
        return Response::redirect($url);
    }

    protected function back(Request $request): Response
    {
        $referer = $request->header('Referer');

        return Response::redirect($referer !== '' ? $referer : '/admin');
    }

    protected function withFlash(string $type, string $message): void
    {
        $_SESSION['irj_flash'] = ['type' => $type, 'message' => $message];
    }

    /** @return array{type: string, message: string}|null */
    protected function pullFlash(): ?array
    {
        $flash = $_SESSION['irj_flash'] ?? null;
        unset($_SESSION['irj_flash']);

        return is_array($flash) ? $flash : null;
    }

    protected function old(Request $request, string $key, string $default = ''): string
    {
        return $request->str($key, $default);
    }

    /** Gate: returns a 403 response when the user lacks ALL given permissions. */
    protected function denyUnlessCan(string ...$permissions): ?Response
    {
        if (!$this->auth->can(...$permissions)) {
            return Response::html($this->view->render('errors.403', [
                'flash' => $this->pullFlash(),
            ]), 403);
        }

        return null;
    }
}
