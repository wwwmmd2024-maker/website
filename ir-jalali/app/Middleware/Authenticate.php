<?php

declare(strict_types=1);

namespace IRJalali\App\Middleware;

use Closure;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Middleware;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;

final class Authenticate implements Middleware
{
    public function __construct(private readonly Auth $auth)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (!$this->auth->check()) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'error' => 'unauthenticated'], 401);
            }

            return Response::redirect('/admin/login');
        }

        return $next($request);
    }
}
