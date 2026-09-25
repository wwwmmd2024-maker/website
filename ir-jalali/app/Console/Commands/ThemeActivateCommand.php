<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Themes\ThemeManager;

final class ThemeActivateCommand extends Command
{
    public function __construct(private readonly ThemeManager $themes)
    {
    }

    public function name(): string
    {
        return 'theme:activate';
    }

    public function description(): string
    {
        return 'Switch the active theme (content is never touched).';
    }

    public function usage(): string
    {
        return 'theme:activate <slug>';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $slug = $args[0] ?? '';
        if ($slug === '') {
            $out->error('Provide a theme slug.');
            return 1;
        }
        $result = $this->themes->activate($slug);
        if (empty($result['ok'])) {
            $out->error((string) ($result['error'] ?? 'Activation failed.'));
            return 1;
        }
        $out->success("Theme [{$slug}] is now active.");

        return 0;
    }
}
