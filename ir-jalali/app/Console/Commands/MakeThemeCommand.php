<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Kernel\Application;

final class MakeThemeCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
    }

    public function name(): string
    {
        return 'make:theme';
    }

    public function description(): string
    {
        return 'Scaffold a new working theme (manifest, templates, parts, stylesheet).';
    }

    public function usage(): string
    {
        return 'make:theme <slug> [--name=..] [--author=..]';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $slug = strtolower($args[0] ?? '');
        if (!preg_match('/^[a-z][a-z0-9\\-]{1,58}[a-z0-9]$/', $slug)) {
            $out->error('Slug must be kebab-case, 3-60 chars (e.g. ir-starter).');
            return 1;
        }
        $dir = $this->app->basePath('themes/' . $slug);
        if (is_dir($dir)) {
            $out->error("Directory already exists: themes/{$slug}");
            return 1;
        }
        $name = (string) ($this->option($options, 'name') ?? $out->ask('Theme name', $slug));
        $author = (string) ($this->option($options, 'author', '') ?? '');

        mkdir($dir . '/templates', 0755, true);
        mkdir($dir . '/parts', 0755, true);

        $files = [
            'theme.json' => $this->manifest($slug, $name, $author),
            'functions.php' => $this->functions($slug),
            'style.css' => $this->stylesheet($slug),
            'parts/header.php' => $this->partHeader(),
            'parts/footer.php' => $this->partFooter(),
            'parts/flash.php' => $this->partFlash(),
            'templates/singular.php' => $this->tplSingular(),
            'templates/page.php' => "<?php /** Page template. */\n\$postMeta = '';\nrequire __DIR__ . '/singular.php';\n",
            'templates/single.php' => "<?php /** Single post template. */\nrequire __DIR__ . '/singular.php';\n",
            'templates/index.php' => $this->tplIndex(),
            'templates/archive.php' => $this->tplArchive(),
            'templates/search.php' => "<?php /** Search results. */\nrequire __DIR__ . '/archive.php';\n",
            'templates/404.php' => $this->tpl404(),
        ];
        foreach ($files as $relative => $contents) {
            file_put_contents($dir . '/' . $relative, $contents);
        }

        $out->success("Theme scaffold created: themes/{$slug}/");
        $out->info("Next: irj theme:activate {$slug}");
        return 0;
    }

    private function manifest(string $slug, string $name, string $author): string
    {
        return json_encode([
            'name' => $name !== '' ? $name : $slug,
            'slug' => $slug,
            'version' => '1.0.0',
            'author' => $author,
            'description' => '',
            'requires' => ['ir-jalali' => '>=1.0.0'],
            'supports' => ['builder', 'widgets', 'menus', 'featured-image', 'rtl'],
            'settings' => ['sidebar' => false, 'container' => '1200px'],
            'templates' => ['index', 'singular', 'page', 'single', 'archive', 'search', '404'],
            'templateParts' => ['header', 'footer'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function functions(string $slug): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

/**
 * {$slug} — theme functions (loaded on every frontend request).
 *
 * Available: \$hooks (HookManager), \$theme (Theme).
 * Example:
 *   \$hooks->addFilter('the_content', fn (string \$html): string => \$html);
 */
PHP;
    }

    private function stylesheet(string $slug): string
    {
        return <<<CSS
/* {$slug} v1.0.0 */
:root { --font: Tahoma, sans-serif; }
body { font-family: var(--font); margin: 0; line-height: 1.9; }
.wrap { max-width: 1200px; margin-inline: auto; padding-inline: 20px; }
.site-header { border-bottom: 1px solid #e5e7eb; padding: 14px 0; }
.site-header .wrap { display: flex; align-items: center; justify-content: space-between; }
.site-title { font-weight: 800; font-size: 20px; text-decoration: none; color: inherit; }
.site-nav { display: flex; gap: 16px; }
.site-nav a { text-decoration: none; color: inherit; }
.site-footer { border-top: 1px solid #e5e7eb; margin-top: 40px; padding: 20px 0; color: #6b7280; font-size: 13px; }
.article h1 { font-size: 28px; }
.post-list { list-style: none; padding: 0; display: grid; gap: 18px; }
.post-list a { font-weight: 700; }
.meta { color: #6b7280; font-size: 13px; margin-bottom: 12px; }
CSS;
    }

    private function partHeader(): string
    {
        return <<<'PHP'
<?php /** Header part. Data: $e, $siteTitle, $tagline, $menu. */ ?>
<header class="site-header"><div class="wrap">
  <a class="site-title" href="/"><?= $e($siteTitle) ?></a>
  <?php if (!empty($tagline)): ?><span><?= $e($tagline) ?></span><?php endif; ?>
  <?php if (!empty($menu)): ?><nav class="site-nav" aria-label="main">
    <?php foreach ($menu as $item): ?><a href="<?= $e($item['url'] ?? '#') ?>"><?= $e($item['title'] ?? '') ?></a><?php endforeach; ?>
  </nav><?php endif; ?>
</div></header>
PHP;
    }

    private function partFooter(): string
    {
        return <<<'PHP'
<?php /** Footer part. Data: $e, $siteTitle. */ ?>
<footer class="site-footer"><div class="wrap">© <?= $e($siteTitle) ?> — قدرت‌گرفته از IR-Jalali</div></footer>
PHP;
    }

    private function partFlash(): string
    {
        return <<<'PHP'
<?php /** Flash message. Data: $flash, $e. */ ?>
<?php if (!empty($flash) && is_array($flash)): ?>
<div class="ij-flash ij-flash-<?= $e($flash['type'] ?? 'info') ?>" role="status"><?= $e($flash['message'] ?? '') ?></div>
<?php endif; ?>
PHP;
    }

    private function docHead(string $title): string
    {
        return <<<'PHP'
<!DOCTYPE html>
<html lang="<?= $e($lang) ?>" dir="<?= $e($dir) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
PHP . "\n<title>{$title}</title>\n" . <<<'PHP'
<link rel="stylesheet" href="<?= $e($frontCssUrl) ?>">
<?php foreach (($themeCssUrls ?? []) as $cssUrl): ?><link rel="stylesheet" href="<?= $e($cssUrl) ?>"><?php endforeach; ?>
<style><?= $headCss ?></style>
</head>
<body>
<?= $headerHtml ?>
<?php require __DIR__ . '/../parts/flash.php'; ?>
PHP;
    }

    private function docFoot(): string
    {
        return <<<'PHP'
<?= $footerHtml ?>
<script src="<?= $e($frontJsUrl) ?>"></script>
</body>
</html>
PHP;
    }

    private function tplSingular(): string
    {
        return "<?php /** Singular wrapper (page + single). */ ?>\n"
            . $this->docHead('<?= $e($pageTitle) ?> — <?= $e($siteTitle) ?>')
            . <<<'PHP'
<main class="wrap"><article class="article">
  <h1><?= $e($post['title'] ?? '') ?></h1>
  <?php if (!empty($postMeta)): ?><div class="meta"><?= $e($postMeta) ?></div><?php endif; ?>
  <?= $content ?>
</article></main>
PHP . "\n" . $this->docFoot() . "\n";
    }

    private function tplIndex(): string
    {
        return "<?php /** Blog index. */ ?>\n"
            . $this->docHead('<?= $e($pageTitle) ?>')
            . <<<'PHP'
<main class="wrap"><h1><?= $e($pageTitle) ?></h1>
<?php if (!empty($posts)): ?><ul class="post-list">
  <?php foreach ($posts as $p): ?><li><a href="/<?= $e($p['slug'] ?? '') ?>"><?= $e($p['title'] ?? '') ?></a></li><?php endforeach; ?>
</ul><?php endif; ?>
<?= $content ?></main>
PHP . "\n" . $this->docFoot() . "\n";
    }

    private function tplArchive(): string
    {
        return str_replace('Blog index.', 'Archive.', $this->tplIndex());
    }

    private function tpl404(): string
    {
        return "<?php /** 404 page. */ ?>\n"
            . $this->docHead('صفحه یافت نشد — <?= $e($siteTitle) ?>')
            . <<<'PHP'
<main class="wrap"><h1>۴۰۴ — صفحه یافت نشد</h1><p><a href="/">بازگشت به خانه</a></p></main>
PHP . "\n" . $this->docFoot() . "\n";
    }
}
