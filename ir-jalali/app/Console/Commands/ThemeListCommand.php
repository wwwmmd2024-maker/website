<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Themes\ThemeManager;

final class ThemeListCommand extends Command
{
    public function __construct(private readonly ThemeManager $themes)
    {
    }

    public function name(): string
    {
        return 'theme:list';
    }

    public function description(): string
    {
        return 'List all installed themes.';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $themes = $this->themes->all();
        if ($themes === []) {
            $out->info('No themes found.');
            return 0;
        }
        $active = $this->themes->activeSlug();
        $rows = [];
        foreach ($themes as $theme) {
            $rows[] = [
                $theme->slug,
                mb_substr($theme->name, 0, 40),
                $theme->version,
                $theme->slug === $active ? 'active' : '',
                mb_substr($theme->author, 0, 24),
            ];
        }
        $out->table(['Slug', 'Name', 'Version', 'Status', 'Author'], $rows);

        return 0;
    }
}
