<?php

declare(strict_types=1);

namespace IRJalali\App\Middleware;

use Closure;
use IRJalali\App\Services\ApiTokenService;
use IRJalali\Core\Http\Middleware;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;

final class ApiAuthenticate implements Middleware
{
    public function __construct(private readonly ApiTokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            return Response::json(['ok' => false, 'error' => 'missing_token'], 401);
        }
        $row = $this->tokens->authenticate($token);
        if ($row === null) {
            return Response::json(['ok' => false, 'error' => 'invalid_token'], 401);
        }
        $request->setAttribute('api_token', $row);
        $request->setAttribute('api_user', $row['user']);

        return $next($request);
    }
}
