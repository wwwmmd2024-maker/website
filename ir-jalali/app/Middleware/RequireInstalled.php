<?php

declare(strict_types=1);

namespace IRJalali\App\Middleware;

use Closure;
use IRJalali\Core\Http\Middleware;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;

final class RequireInstalled implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Application::get()->isInstalled()) {
            return Response::redirect('/install');
        }

        return $next($request);
    }
}
