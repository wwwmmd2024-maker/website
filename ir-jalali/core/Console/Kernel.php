<?php

declare(strict_types=1);

namespace IRJalali\Core\Console;

use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Plugins\PluginManager;

/**
 * Dispatches `irj` commands (core commands + plugin-registered commands).
 */
final class Kernel
{
    /** @var array<string, class-string<Command>> */
    private array $commands = [];

    public function __construct(
        private readonly Application $app,
        private readonly Output $out,
    ) {
    }

    /** @param class-string<Command> $class */
    public function register(string $class): void
    {
        /** @var Command $command */
        $command = $this->app->make($class);
        $this->commands[$command->name()] = $class;
    }

    public function run(array $argv): int
    {
        array_shift($argv); // binary name
        [$name, $args, $options] = $this->parse($argv);

        if ($name === null || $name === 'help' || isset($options['help']) || isset($options['h'])) {
            $this->printHelp($name !== null && $name !== 'help' ? $name : ($args[0] ?? null));

            return 0;
        }
        if ($name === 'list') {
            $this->printList();

            return 0;
        }

        if (isset($this->commands[$name])) {
            /** @var Command $command */
            $command = $this->app->make($this->commands[$name]);
            try {
                return $command->handle($args, $options, $this->out);
            } catch (\Throwable $e) {
                $this->out->error($e->getMessage());

                return 1;
            }
        }

        $pluginCommand = $this->pluginCommands()[$name] ?? null;
        if ($pluginCommand !== null) {
            try {
                return (int) $this->invokeHandler($pluginCommand['handler'], $args, $options);
            } catch (\Throwable $e) {
                $this->out->error("[{$pluginCommand['plugin']}] {$e->getMessage()}");

                return 1;
            }
        }

        $this->out->error("Unknown command [{$name}]. Run `irj list` to see all commands.");

        return 1;
    }

    /** @return array{0: string|null, 1: list<string>, 2: array<string, string|bool>} */
    private function parse(array $argv): array
    {
        $name = null;
        $args = [];
        $options = [];
        foreach ($argv as $token) {
            if (str_starts_with($token, '--')) {
                $pair = substr($token, 2);
                if (str_contains($pair, '=')) {
                    [$key, $value] = explode('=', $pair, 2);
                    $options[$key] = $value;
                } else {
                    $options[$pair] = true;
                }
                continue;
            }
            if ($name === null) {
                $name = $token;
                continue;
            }
            $args[] = $token;
        }

        return [$name, $args, $options];
    }

    private function printHelp(?string $name): void
    {
        if ($name !== null && isset($this->commands[$name])) {
            /** @var Command $command */
            $command = $this->app->make($this->commands[$name]);
            $this->out->writeln('Usage: irj ' . $command->usage());
            $this->out->writeln('');
            $this->out->writeln($command->description());

            return;
        }
        $pluginCommand = $name !== null ? ($this->pluginCommands()[$name] ?? null) : null;
        if ($pluginCommand !== null) {
            $this->out->writeln("Usage: irj {$name} (from plugin [{$pluginCommand['plugin']}])");
            $this->out->writeln('');
            $this->out->writeln($pluginCommand['description']);

            return;
        }
        $this->out->writeln('IR-Jalali Website OS — command line');
        $this->out->writeln('');
        $this->out->writeln('Usage: irj <command> [args] [--option=value]');
        $this->out->writeln('');
        $this->printList();
    }

    private function printList(): void
    {
        $rows = [];
        foreach ($this->commands as $name => $class) {
            /** @var Command $command */
            $command = $this->app->make($class);
            $rows[] = [$name, $command->description(), 'core'];
        }
        foreach ($this->pluginCommands() as $name => $command) {
            $rows[] = [$name, $command['description'], 'plugin:' . $command['plugin']];
        }
        usort($rows, fn (array $a, array $b): int => strcmp($a[0], $b[0]));
        $this->out->table(['Command', 'Description', 'Source'], $rows);
    }

    /** @return array<string, array{name: string, description: string, handler: callable, plugin: string}> */
    private function pluginCommands(): array
    {
        try {
            /** @var PluginManager $plugins */
            $plugins = $this->app->make(PluginManager::class);

            return $plugins->commands();
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Plugin handlers receive (args, options, out) — arity-tolerant.
     *
     * @param list<string> $args
     * @param array<string, string|bool> $options
     */
    private function invokeHandler(callable $handler, array $args, array $options): mixed
    {
        $callable = \Closure::fromCallable($handler);
        $arity = (new \ReflectionFunction($callable))->getNumberOfParameters();
        $params = [$args, $options, $this->out];

        return $callable(...array_slice($params, 0, $arity));
    }
}
