<?php

declare(strict_types=1);

/**
 * Plugin-collected routes are mounted here, AFTER every core route,
 * so a plugin can never shadow core/admin URLs.
 */

use IRJalali\Core\Http\Router;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginManager;

/** @var Router $router */
if (Application::get()->isInstalled()) {
    /** @var PluginManager $pluginManager */
    $pluginManager = Application::get()->make(PluginManager::class);
    $pluginManager->boot();
    foreach ($pluginManager->routes() as $pluginRoute) {
        $router->match($pluginRoute['methods'], $pluginRoute['path'], $pluginRoute['handler'])
            ->middleware(...$pluginRoute['middleware']);
    }
    unset($pluginManager, $pluginRoute);
}
