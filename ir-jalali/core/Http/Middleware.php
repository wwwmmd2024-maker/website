<?php

declare(strict_types=1);

namespace IRJalali\Core\Http;

use Closure;

/**
 * Contract for every HTTP middleware.
 */
interface Middleware
{
    public function handle(Request $request, Closure $next): Response;
}
