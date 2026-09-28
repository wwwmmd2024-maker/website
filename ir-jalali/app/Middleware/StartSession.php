<?php

declare(strict_types=1);

namespace IRJalali\App\Middleware;

use Closure;
use IRJalali\Core\Hooks\Hooks;
use IRJalali\Core\Http\Middleware;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Security\Session;

final class StartSession implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /**
         * Extension point: IR-Security (and any plugin) can veto an IP before
         * the session even starts. Fail-closed only when a plugin says so.
         */
        try {
            $blocked = Application::get()->make(Hooks::class)->applyFilters('security.block_ip', false, $request->ip(), $request);
        } catch (\Throwable) {
            $blocked = false;
        }
        if ($blocked === true) {
            return Response::html('<h1>403</h1><p>Forbidden</p>', 403);
        }

        Session::start();

        return $next($request);
    }
}
