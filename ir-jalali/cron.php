<?php

declare(strict_types=1);

/**
 * Cron entrypoint — run every minute:
 *   php /path/to/ir-jalali/cron.php
 *
 * Web access is blocked (CLI only) unless CRON_WEB_TOKEN matches ?token=.
 */

use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Scheduler\Scheduler;

require __DIR__ . '/core/Kernel/Autoloader.php';
\IRJalali\Core\Kernel\Autoloader::register(__DIR__);

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $expected = (string) ($_ENV['CRON_WEB_TOKEN'] ?? getenv('CRON_WEB_TOKEN') ?? '');
    $given = (string) ($_GET['token'] ?? '');
    if ($expected === '' || !hash_equals($expected, $given)) {
        http_response_code(403);
        exit('Forbidden');
    }
}

$app = Application::boot(__DIR__);
require __DIR__ . '/app/bootstrap.php';

if (!$app->isInstalled()) {
    exit("Not installed.\n");
}

// Boot active plugins so their cron handlers register before runDue().
try {
    $app->make(\IRJalali\Core\Plugins\PluginManager::class)->boot();
} catch (\Throwable $e) {
    $app->make(\IRJalali\Core\Logging\Logger::class)->channel('scheduler')
        ->error('Plugin boot failed during cron', ['error' => $e->getMessage()]);
}

$count = $app->make(Scheduler::class)->runDue();
echo 'Executed ' . $count . " task(s).\n";
