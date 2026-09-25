<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers;

use IRJalali\App\Services\AuditService;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Config\Config;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Security\RateLimiter;
use IRJalali\Core\Translation\Translator;
use IRJalali\Core\View\View;

final class AuthController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly RateLimiter $limiter,
        private readonly Config $config,
        private readonly AuditService $audit,
        private readonly Translator $t,
    ) {
        parent::__construct($view, $auth);
    }

    public function showLogin(Request $request): Response
    {
        return $this->render('auth.login', []);
    }

    public function login(Request $request): Response
    {
        $login = $request->str('login');
        $password = (string) $request->input('password', '');
        $key = 'login:' . $request->ip() . ':' . mb_substr($login, 0, 60);
        $max = (int) $this->config->get('security.login_max_attempts', 5);
        $decay = (int) $this->config->get('security.login_decay_seconds', 300);

        if ($this->limiter->tooManyAttempts($key, $max)) {
            $seconds = $this->limiter->availableIn($key);
            $this->audit->security('login.throttled', null, ['login' => $login]);
            $this->withFlash('error', $this->t->get('auth.throttled', ['seconds' => (string) $seconds]));

            return $this->redirect('/admin/login');
        }

        if ($login === '' || $password === '' || !$this->auth->attempt($login, $password)) {
            $this->limiter->hit($key, $decay);
            $this->audit->loginFailed($login);
            // Uniform timing to reduce user-enumeration signal.
            usleep(random_int(80_000, 160_000));
            $this->withFlash('error', $this->t->get('auth.failed'));

            return $this->redirect('/admin/login');
        }

        $this->limiter->clear($key);
        $user = $this->auth->user();
        $this->audit->loginSuccess((int) $user['id']);
        $name = (string) ($user['display_name'] ?: $user['username']);
        $this->withFlash('success', $this->t->get('auth.welcome', ['name' => $name]));

        return $this->redirect('/admin');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();

        return $this->redirect('/admin/login');
    }
}
