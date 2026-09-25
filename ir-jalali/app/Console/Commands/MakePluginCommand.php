<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Kernel\Application;

final class MakePluginCommand extends Command
{
    public function __construct(private readonly Application $app)
    {
    }

    public function name(): string
    {
        return 'make:plugin';
    }

    public function description(): string
    {
        return 'Scaffold a new plugin (manifest, provider, blocks autoload, migrations dir).';
    }

    public function usage(): string
    {
        return 'make:plugin <slug> [--name=..] [--author=..] [--desc=..]';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $slug = strtolower($args[0] ?? '');
        if (!preg_match('/^[a-z][a-z0-9\\-]{1,58}[a-z0-9]$/', $slug)) {
            $out->error('Slug must be kebab-case, 3-60 chars (e.g. ir-hello).');
            return 1;
        }
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug)));
        $dir = $this->app->basePath('plugins/' . $studly);
        if (is_dir($dir)) {
            $out->error("Directory already exists: plugins/{$studly}");
            return 1;
        }
        $name = (string) ($this->option($options, 'name') ?? $out->ask('Plugin name', $studly));
        $author = (string) ($this->option($options, 'author', '') ?? '');
        $desc = (string) ($this->option($options, 'desc', '') ?? '');

        mkdir($dir . '/blocks', 0755, true);
        mkdir($dir . '/migrations', 0755, true);
        file_put_contents($dir . '/plugin.json', $this->manifest($slug, $name, $author, $desc));
        file_put_contents($dir . '/Plugin.php', $this->provider($slug, $studly));
        file_put_contents($dir . '/README.md', "# {$name}\n\n{$desc}\n");
        file_put_contents($dir . '/blocks/.gitkeep', '');
        file_put_contents($dir . '/migrations/.gitkeep', '');

        $out->success("Plugin scaffold created: plugins/{$studly}/");
        $out->info("Next: irj plugin:activate {$slug} --yes");
        return 0;
    }

    private function manifest(string $slug, string $name, string $author, string $desc): string
    {
        return json_encode([
            'slug' => $slug,
            'name' => $name !== '' ? $name : $slug,
            'version' => '1.0.0',
            'description' => $desc,
            'author' => $author,
            'license' => 'MIT',
            'requires' => ['core' => '>=1.1.0', 'php' => '>=8.2'],
            'capabilities' => [],
            'autoload' => ['psr-4' => ["IRJalali\\Plugins\\{$this->studly($slug)}\\" => '']],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }

    private function provider(string $slug, string $studly): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace IRJalali\\Plugins\\{$studly};

use IRJalali\\Core\\Plugins\\PluginContext;
use IRJalali\\Core\\Plugins\\PluginServiceProvider;
use IRJalali\\Core\\Sdk\\IRJalali;

final class Plugin extends PluginServiceProvider
{
    public function boot(PluginContext \$context): void
    {
        \$sdk = IRJalali::for('{$slug}');

        // Auto-register every block in blocks/ (each file returns its definition array).
        foreach ((array) glob(__DIR__ . '/blocks/*.php') as \$file) {
            \$definition = require \$file;
            if (is_array(\$definition)) {
                \$sdk->registerBlock(\$definition);
            }
        }
    }

    public function activate(): void
    {
    }

    public function deactivate(): void
    {
    }

    public function uninstall(): void
    {
    }
}
PHP;
    }

    private function studly(string $slug): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug)));
    }
}
