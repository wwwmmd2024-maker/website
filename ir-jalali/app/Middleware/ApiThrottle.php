<?php

declare(strict_types=1);

namespace IRJalali\App\Middleware;

use Closure;
use IRJalali\Core\Http\Middleware;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Security\RateLimiter;

final class ApiThrottle implements Middleware
{
    public function __construct(private readonly RateLimiter $limiter)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $key = 'api:' . $request->ip();
        if ($this->limiter->tooManyAttempts($key, 60)) {
            return Response::json(['ok' => false, 'error' => 'rate_limited'], 429);
        }
        $this->limiter->hit($key, 60);

        return $next($request);
    }
}
