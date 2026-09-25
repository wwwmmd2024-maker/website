<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Api;

use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Kernel\Application;

final class StatusController
{
    public function show(Request $request): Response
    {
        $app = Application::get();

        return Response::json([
            'ok' => true,
            'product' => 'IR-Jalali',
            'version' => $app->version(),
            'api' => 'v1',
            'installed' => $app->isInstalled(),
            'time' => date('c'),
        ]);
    }
}
