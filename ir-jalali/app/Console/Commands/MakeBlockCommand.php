<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginManager;

final class MakeBlockCommand extends Command
{
    public function __construct(
        private readonly Application $app,
        private readonly PluginManager $plugins,
    ) {
    }

    public function name(): string
    {
        return 'make:block';
    }

    public function description(): string
    {
        return 'Scaffold a block inside a plugin (auto-registered from the blocks/ directory).';
    }

    public function usage(): string
    {
        return 'make:block <plugin-slug> <block-slug> [--title=..]';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $pluginSlug = strtolower($args[0] ?? '');
        $blockSlug = strtolower($args[1] ?? '');
        if ($pluginSlug === '' || $blockSlug === '') {
            $out->error('Usage: irj ' . $this->usage());
            return 1;
        }
        if (!preg_match('/^[a-z0-9\\-]{2,60}$/', $blockSlug)) {
            $out->error('Block slug must be kebab-case (e.g. hero-banner).');
            return 1;
        }
        $plugins = $this->plugins->discover();
        $plugin = $plugins[$pluginSlug] ?? null;
        if ($plugin === null) {
            $out->error("Plugin [{$pluginSlug}] not found. Create it first: irj make:plugin {$pluginSlug}");
            return 1;
        }
        $blocksDir = $plugin->path . '/blocks';
        if (!is_dir($blocksDir)) {
            mkdir($blocksDir, 0755, true);
        }
        $studly = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $blockSlug)));
        $file = $blocksDir . '/' . $studly . '.php';
        if (is_file($file)) {
            $out->error("Block file already exists: {$file}");
            return 1;
        }
        $vendor = explode('-', $pluginSlug)[0];
        $title = (string) ($this->option($options, 'title') ?? $out->ask('Block title', $studly));
        file_put_contents($file, $this->blockClass($pluginSlug, $studly, $vendor . '/' . $blockSlug, $title));
        $out->success("Block created: {$file}");

        return 0;
    }

    private function blockClass(string $pluginSlug, string $studly, string $slug, string $title): string
    {
        $namespace = 'IRJalali\\Plugins\\' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $pluginSlug))) . '\\Blocks';

        return <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use IRJalali\\Core\\Builder\\RenderContext;

final class {$studly}
{
    /** Block definition (consumed by the plugin's blocks/ autoloader). */
    public static function definition(): array
    {
        return [
            'slug' => '{$slug}',
            'title' => '{$this->escape($title)}',
            'category' => 'general',
            'icon' => '▣',
            'description' => '',
            'schema' => [
                ['key' => 'heading', 'type' => 'text', 'label' => 'سربرگ'],
                ['key' => 'align', 'type' => 'select', 'label' => 'تراز', 'options' => ['right', 'center', 'left']],
            ],
            'defaults' => ['heading' => '', 'align' => 'center'],
            'render' => [self::class, 'render'],
        ];
    }

    /** @param array<string, mixed> \$data */
    public static function render(array \$data, RenderContext \$ctx): string
    {
        \$heading = trim((string) (\$data['heading'] ?? ''));
        if (\$heading === '') {
            return '';
        }
        \$align = in_array(\$data['align'] ?? 'center', ['right', 'center', 'left'], true) ? \$data['align'] : 'center';

        return '<div class="ij-block-{$this->cssClass($slug)} ij-align-' . \$align . '">'
            . htmlspecialchars(\$heading, ENT_QUOTES, 'UTF-8') . '</div>';
    }
}

return {$studly}::definition();
PHP;
    }

    private function escape(string $value): string
    {
        return str_replace("'", "\\'", $value);
    }

    private function cssClass(string $slug): string
    {
        return preg_replace('/[^a-z0-9]+/', '-', strtolower($slug)) ?? 'block';
    }
}
