<?php

declare(strict_types=1);

use IRJalali\App\Controllers\Admin\BackupsController;
use IRJalali\App\Controllers\Admin\BuilderController;
use IRJalali\App\Controllers\Admin\CptController;
use IRJalali\App\Controllers\Admin\DashboardController;
use IRJalali\App\Controllers\Admin\FieldsController;
use IRJalali\App\Controllers\Admin\FormController;
use IRJalali\App\Controllers\Admin\LogController;
use IRJalali\App\Controllers\Admin\MarketplaceController;
use IRJalali\App\Controllers\Admin\MediaController;
use IRJalali\App\Controllers\Admin\MenuController;
use IRJalali\App\Controllers\Admin\ModesController;
use IRJalali\App\Controllers\Admin\NotificationsController;
use IRJalali\App\Controllers\Admin\PageController;
use IRJalali\App\Controllers\Admin\PluginPageController;
use IRJalali\App\Controllers\Admin\PluginsController;
use IRJalali\App\Controllers\Admin\PostController as AdminPostController;
use IRJalali\App\Controllers\Admin\SettingsController;
use IRJalali\App\Controllers\Admin\SystemController;
use IRJalali\App\Controllers\Admin\TemplateController;
use IRJalali\App\Controllers\Admin\ThemesController;
use IRJalali\App\Controllers\Admin\TypesController;
use IRJalali\App\Controllers\Admin\UpdatesController;
use IRJalali\App\Controllers\Admin\UserController;
use IRJalali\App\Controllers\Admin\WidgetsController;
use IRJalali\App\Controllers\PublicFormController;
use IRJalali\App\Controllers\Api\PostController as ApiPostController;
use IRJalali\App\Controllers\Api\ResourceController as ApiResourceController;
use IRJalali\App\Controllers\Api\StatusController;
use IRJalali\App\Controllers\AuthController;
use IRJalali\App\Controllers\FrontController;
use IRJalali\App\Controllers\HomeController;
use IRJalali\App\Controllers\ThemeAssetController;
use IRJalali\App\Controllers\InstallController;
use IRJalali\App\Controllers\SetupController;
use IRJalali\App\Middleware\ApiAuthenticate;
use IRJalali\App\Middleware\ApiThrottle;
use IRJalali\App\Middleware\Authenticate;
use IRJalali\App\Middleware\Guest;
use IRJalali\App\Middleware\RequireInstalled;
use IRJalali\App\Middleware\RequireNotInstalled;
use IRJalali\App\Middleware\SecurityHeaders;
use IRJalali\App\Middleware\StartSession;
use IRJalali\App\Middleware\VerifyCsrf;
use IRJalali\Core\Http\Router;

/** @var Router $router */

$web = [StartSession::class, SecurityHeaders::class];

// ── Installer (only before install) ──────────────────────────────
$router->group(['middleware' => [...$web, RequireNotInstalled::class]], function (Router $r): void {
    $r->get('/install', [InstallController::class, 'index'])->name('install');
    $r->post('/install', [InstallController::class, 'store'])
        ->middleware(VerifyCsrf::class)->name('install.store');
});

// ── First-run setup wizard ───────────────────────────────────────
$router->group(['middleware' => [...$web, RequireInstalled::class, Authenticate::class]], function (Router $r): void {
    $r->get('/setup', [SetupController::class, 'index'])->name('setup');
    $r->post('/setup', [SetupController::class, 'apply'])
        ->middleware(VerifyCsrf::class)->name('setup.apply');
});

// ── Admin ────────────────────────────────────────────────────────
$router->group(['prefix' => 'admin', 'middleware' => [...$web, RequireInstalled::class]], function (Router $r): void {
    $r->get('/login', [AuthController::class, 'showLogin'])->middleware(Guest::class)->name('login');
    $r->post('/login', [AuthController::class, 'login'])->middleware(Guest::class, VerifyCsrf::class)->name('login.store');
    $r->post('/logout', [AuthController::class, 'logout'])->middleware(Authenticate::class, VerifyCsrf::class)->name('logout');

    $r->get('', [DashboardController::class, 'index'])->middleware(Authenticate::class)->name('admin');
    $r->get('/', [DashboardController::class, 'index'])->middleware(Authenticate::class);

    $r->get('/settings', [SettingsController::class, 'index'])->middleware(Authenticate::class)->name('admin.settings');
    $r->post('/settings', [SettingsController::class, 'update'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/users', [UserController::class, 'index'])->middleware(Authenticate::class)->name('admin.users');
    $r->get('/logs', [LogController::class, 'index'])->middleware(Authenticate::class)->name('admin.logs');

    $r->get('/media', [MediaController::class, 'index'])->middleware(Authenticate::class)->name('admin.media');
    $r->post('/media/upload', [MediaController::class, 'upload'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/media/{id}/delete', [MediaController::class, 'destroy'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/pages', [PageController::class, 'index'])->middleware(Authenticate::class)->name('admin.pages');
    $r->get('/pages/create', [PageController::class, 'create'])->middleware(Authenticate::class);
    $r->post('/pages', [PageController::class, 'store'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/pages/{id}/edit', [PageController::class, 'edit'])->middleware(Authenticate::class);
    $r->post('/pages/{id}', [PageController::class, 'update'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/pages/{id}/delete', [PageController::class, 'destroy'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/posts', [AdminPostController::class, 'index'])->middleware(Authenticate::class)->name('admin.posts');
    $r->get('/posts/create', [AdminPostController::class, 'create'])->middleware(Authenticate::class);
    $r->post('/posts', [AdminPostController::class, 'store'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/posts/{id}/edit', [AdminPostController::class, 'edit'])->middleware(Authenticate::class);
    $r->post('/posts/{id}', [AdminPostController::class, 'update'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/posts/{id}/delete', [AdminPostController::class, 'destroy'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/templates', [TemplateController::class, 'index'])->middleware(Authenticate::class)->name('admin.templates');
    $r->get('/templates/create', [TemplateController::class, 'create'])->middleware(Authenticate::class);
    $r->post('/templates', [TemplateController::class, 'store'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/templates/{id}/edit', [TemplateController::class, 'edit'])->middleware(Authenticate::class);
    $r->post('/templates/{id}', [TemplateController::class, 'update'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/templates/{id}/delete', [TemplateController::class, 'destroy'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/menus', [MenuController::class, 'index'])->middleware(Authenticate::class)->name('admin.menus');
    $r->post('/menus', [MenuController::class, 'store'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/menus/{id}/edit', [MenuController::class, 'edit'])->middleware(Authenticate::class);
    $r->post('/menus/{id}', [MenuController::class, 'update'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/menus/{id}/delete', [MenuController::class, 'destroy'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/menus/{id}/items', [MenuController::class, 'storeItem'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/menus/{id}/items/{item}', [MenuController::class, 'updateItem'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/menus/{id}/items/{item}/delete', [MenuController::class, 'destroyItem'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/forms', [FormController::class, 'index'])->middleware(Authenticate::class)->name('admin.forms');
    $r->get('/forms/create', [FormController::class, 'create'])->middleware(Authenticate::class);
    $r->post('/forms', [FormController::class, 'store'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/forms/{id}/edit', [FormController::class, 'edit'])->middleware(Authenticate::class);
    $r->post('/forms/{id}', [FormController::class, 'update'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/forms/{id}/delete', [FormController::class, 'destroy'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/forms/{id}/fields', [FormController::class, 'fields'])->middleware(Authenticate::class);
    $r->post('/forms/{id}/fields', [FormController::class, 'storeField'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/forms/{id}/fields/{field}/delete', [FormController::class, 'destroyField'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/forms/{id}/fields/{field}/move', [FormController::class, 'moveField'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/forms/{id}/submissions', [FormController::class, 'submissions'])->middleware(Authenticate::class);
    $r->get('/forms/{id}/submissions/{submission}', [FormController::class, 'showSubmission'])->middleware(Authenticate::class);
    $r->post('/forms/{id}/submissions/{submission}/delete', [FormController::class, 'destroySubmission'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/plugin/{slug}', [PluginPageController::class, 'show'])->middleware(Authenticate::class)->name('admin.plugin');
    $r->post('/plugin/{slug}', [PluginPageController::class, 'show'])->middleware(Authenticate::class, VerifyCsrf::class);

    // ── Part 2/3 managers ────────────────────────────────────────
    $r->get('/plugins', [PluginsController::class, 'index'])->middleware(Authenticate::class)->name('admin.plugins');
    $r->post('/plugins/upload', [PluginsController::class, 'upload'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/plugins/{slug}/activate', [PluginsController::class, 'activate'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/plugins/{slug}/deactivate', [PluginsController::class, 'deactivate'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/plugins/{slug}/update', [PluginsController::class, 'update'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/plugins/{slug}/uninstall', [PluginsController::class, 'uninstall'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/themes', [ThemesController::class, 'index'])->middleware(Authenticate::class)->name('admin.themes');
    $r->post('/themes/upload', [ThemesController::class, 'upload'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/themes/{slug}/activate', [ThemesController::class, 'activate'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/themes/{slug}/remove', [ThemesController::class, 'remove'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/marketplace', [MarketplaceController::class, 'index'])->middleware(Authenticate::class)->name('admin.marketplace');
    $r->post('/marketplace/install', [MarketplaceController::class, 'install'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/updates', [UpdatesController::class, 'index'])->middleware(Authenticate::class)->name('admin.updates');
    $r->post('/updates/{kind}/{slug}/apply', [UpdatesController::class, 'apply'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/backups', [BackupsController::class, 'index'])->middleware(Authenticate::class)->name('admin.backups');
    $r->post('/backups/create', [BackupsController::class, 'create'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/backups/{name}/download', [BackupsController::class, 'download'])->middleware(Authenticate::class);
    $r->post('/backups/{name}/restore', [BackupsController::class, 'restore'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/backups/{name}/delete', [BackupsController::class, 'delete'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/backups/schedule', [BackupsController::class, 'toggleSchedule'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/system', [SystemController::class, 'index'])->middleware(Authenticate::class)->name('admin.system');
    $r->get('/notifications', [NotificationsController::class, 'index'])->middleware(Authenticate::class)->name('admin.notifications');
    $r->post('/notifications/{id}/read', [NotificationsController::class, 'markRead'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/notifications/read-all', [NotificationsController::class, 'markAllRead'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/notifications/{id}/delete', [NotificationsController::class, 'delete'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/notifications/clear-read', [NotificationsController::class, 'clearRead'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/modes', [ModesController::class, 'index'])->middleware(Authenticate::class)->name('admin.modes');
    $r->post('/modes/{mode}/apply', [ModesController::class, 'apply'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/types', [TypesController::class, 'index'])->middleware(Authenticate::class)->name('admin.types');
    $r->post('/types', [TypesController::class, 'store'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/types/{id}/delete', [TypesController::class, 'destroy'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/fields', [FieldsController::class, 'index'])->middleware(Authenticate::class)->name('admin.fields');
    $r->post('/fields/groups', [FieldsController::class, 'storeGroup'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/fields/groups/{id}/delete', [FieldsController::class, 'deleteGroup'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/fields/groups/{id}/fields', [FieldsController::class, 'storeField'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/fields/{field}/delete', [FieldsController::class, 'deleteField'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/widgets', [WidgetsController::class, 'index'])->middleware(Authenticate::class)->name('admin.widgets');
    $r->post('/widgets', [WidgetsController::class, 'store'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/widgets/{id}', [WidgetsController::class, 'update'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/widgets/{id}/move', [WidgetsController::class, 'move'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/widgets/{id}/delete', [WidgetsController::class, 'destroy'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/cpt/{type}', [CptController::class, 'index'])->middleware(Authenticate::class)->name('admin.cpt');
    $r->get('/cpt/{type}/create', [CptController::class, 'create'])->middleware(Authenticate::class);
    $r->post('/cpt/{type}', [CptController::class, 'store'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/cpt/{type}/{id}/edit', [CptController::class, 'edit'])->middleware(Authenticate::class);
    $r->post('/cpt/{type}/{id}', [CptController::class, 'update'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/cpt/{type}/{id}/delete', [CptController::class, 'destroy'])->middleware(Authenticate::class, VerifyCsrf::class);

    $r->get('/builder/{entity}/{id}', [BuilderController::class, 'editor'])->middleware(Authenticate::class)->name('admin.builder');
    $r->get('/api/builder/load', [BuilderController::class, 'apiLoad'])->middleware(Authenticate::class);
    $r->post('/api/builder/save', [BuilderController::class, 'apiSave'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->post('/api/builder/preview', [BuilderController::class, 'apiPreview'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/api/builder/revisions', [BuilderController::class, 'apiRevisions'])->middleware(Authenticate::class);
    $r->get('/api/builder/revision', [BuilderController::class, 'apiRevision'])->middleware(Authenticate::class);
    $r->post('/api/builder/restore', [BuilderController::class, 'apiRestore'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/api/builder/tokens', [BuilderController::class, 'apiTokens'])->middleware(Authenticate::class);
    $r->post('/api/builder/tokens', [BuilderController::class, 'apiTokensSave'])->middleware(Authenticate::class, VerifyCsrf::class);
    $r->get('/api/builder/media', [BuilderController::class, 'apiMedia'])->middleware(Authenticate::class);
});

// ── REST API v1 ──────────────────────────────────────────────────
$router->group(['prefix' => 'api/v1', 'middleware' => [SecurityHeaders::class, RequireInstalled::class, ApiThrottle::class]], function (Router $r): void {
    $r->get('/status', [StatusController::class, 'show'])->name('api.status');
    $r->get('/posts', [ApiPostController::class, 'index'])->middleware(ApiAuthenticate::class)->name('api.posts');
    $r->get('/pages', [ApiResourceController::class, 'pages'])->middleware(ApiAuthenticate::class)->name('api.pages');
    $r->get('/users', [ApiResourceController::class, 'users'])->middleware(ApiAuthenticate::class)->name('api.users');
    $r->get('/media', [ApiResourceController::class, 'media'])->middleware(ApiAuthenticate::class)->name('api.media');
    $r->get('/themes', [ApiResourceController::class, 'themes'])->middleware(ApiAuthenticate::class)->name('api.themes');
    $r->get('/plugins', [ApiResourceController::class, 'plugins'])->middleware(ApiAuthenticate::class)->name('api.plugins');
    $r->get('/blocks', [ApiResourceController::class, 'blocks'])->middleware(ApiAuthenticate::class)->name('api.blocks');
    $r->get('/widgets', [ApiResourceController::class, 'widgets'])->middleware(ApiAuthenticate::class)->name('api.widgets');
    $r->get('/templates', [ApiResourceController::class, 'templates'])->middleware(ApiAuthenticate::class)->name('api.templates');
    $r->get('/forms', [ApiResourceController::class, 'forms'])->middleware(ApiAuthenticate::class)->name('api.forms');
    $r->get('/settings', [ApiResourceController::class, 'settings'])->middleware(ApiAuthenticate::class)->name('api.settings');
    $r->get('/search', [ApiResourceController::class, 'search'])->middleware(ApiAuthenticate::class)->name('api.search');
    $r->get('/menus', [ApiResourceController::class, 'menus'])->middleware(ApiAuthenticate::class)->name('api.menus');
});

// ── Theme assets (jailed proxy; themes live outside docroot) ─────────
$router->group(['middleware' => [...$web, RequireInstalled::class]], function (Router $r): void {
    $r->get('/theme-assets/{theme}/{file*}', [ThemeAssetController::class, 'show'])->name('theme.asset');
});

// ── Frontend (theme engine) ──────────────────────────────────────
$router->group(['middleware' => [...$web, RequireInstalled::class]], function (Router $r): void {
    $r->get('/', [FrontController::class, 'index'])->name('home');
    $r->get('/search', [FrontController::class, 'search'])->name('search');
    $r->post('/forms/submit', [PublicFormController::class, 'submit'])->middleware(VerifyCsrf::class)->name('forms.submit');
    $r->post('/newsletter/subscribe', [PublicFormController::class, 'newsletter'])->middleware(VerifyCsrf::class)->name('newsletter.subscribe');
});

// ── Plugin routes (after specific core routes, before catch-all) ──
require __DIR__ . '/plugin-routes.php';

// ── Catch-all MUST stay last: explicit routes beat post slugs ─────
$router->group(['middleware' => [...$web, RequireInstalled::class]], function (Router $r): void {
    $r->get('/{slug}', [FrontController::class, 'page'])->name('page');
});
