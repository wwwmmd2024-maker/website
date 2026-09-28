<?php

declare(strict_types=1);

namespace IRJalali\App\Middleware;

use Closure;
use IRJalali\Core\Http\Middleware;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Security\SecurityHeaders as Headers;

final class SecurityHeaders implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $strict = str_starts_with($request->path(), '/admin')
            || str_starts_with($request->path(), '/install')
            || str_starts_with($request->path(), '/setup');
        foreach (Headers::all($strict) as $name => $value) {
            $response->withHeader($name, $value);
        }

        return $response;
    }
}
