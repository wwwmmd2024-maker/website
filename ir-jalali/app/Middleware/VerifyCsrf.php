<?php

declare(strict_types=1);

namespace IRJalali\App\Middleware;

use Closure;
use IRJalali\Core\Http\Middleware;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Security\Csrf;

final class VerifyCsrf implements Middleware
{
    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST', 'PUT', 'PATCH', 'DELETE')) {
            $token = $request->input('_token');
            if (!is_string($token) || !$this->csrf->validate($token)) {
                if ($request->wantsJson()) {
                    return Response::json(['ok' => false, 'error' => 'csrf_mismatch'], 419);
                }

                return Response::text('CSRF token mismatch.', 419);
            }
        }

        return $next($request);
    }
}
