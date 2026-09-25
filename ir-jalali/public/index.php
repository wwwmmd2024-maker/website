<?php

declare(strict_types=1);

/**
 * IR-Jalali front controller. Document root MUST point to /public.
 */

use IRJalali\Core\Http\Router;
use IRJalali\Core\Kernel\Application;

define('IRJ_START', microtime(true));

require dirname(__DIR__) . '/core/Kernel/Autoloader.php';

\IRJalali\Core\Kernel\Autoloader::register(dirname(__DIR__));

try {
    $app = Application::boot(dirname(__DIR__));

    require dirname(__DIR__) . '/app/bootstrap.php';

    /** @var Router $router */
    $router = $app->make(Router::class);
    $router->setContainer($app->container());

    require dirname(__DIR__) . '/app/routes.php';

    $request = $app->make(\IRJalali\Core\Http\Request::class);
    $hooks = $app->make(\IRJalali\Core\Hooks\Hooks::class);

    /**
     * Extension point: plugins may short-circuit routing and return a cached
     * Response (IR-Cache full-page cache). Return null to continue normally.
     */
    $shortCircuit = $hooks->applyFilters('http.before', null, $request);
    if ($shortCircuit instanceof \IRJalali\Core\Http\Response) {
        $response = $shortCircuit;
    } else {
        $response = $router->dispatch($request);
    }

    /**
     * Extension point: inspect/replace the outgoing HTTP response.
     * Used by official plugins (IR-Cache page cache, IR-Analytics beacon, …).
     */
    $response = $hooks->applyFilters('http.response', $response, $request);

    $response->send();
} catch (\Throwable $e) {
    $debug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true' || ($_ENV['APP_DEBUG'] ?? '') === '1';
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    $message = $debug
        ? '<h1>خطای سرور</h1><pre dir="ltr">' . htmlspecialchars($e->__toString(), ENT_QUOTES, 'UTF-8') . '</pre>'
        : '<h1>خطای داخلی سرور</h1><p>لطفاً بعداً تلاش کنید.</p>';
    echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>خطا</title></head><body style="font-family:Tahoma;padding:40px">' . $message . '</body></html>';
    error_log('[IR-Jalali] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
}
