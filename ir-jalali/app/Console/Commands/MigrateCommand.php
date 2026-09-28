<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Database\MigrationRunner;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Logging\Logger;

final class MigrateCommand extends Command
{
    public function __construct(
        private readonly Application $app,
        private readonly Database $db,
        private readonly Logger $logger,
    ) {
    }

    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Run pending core database migrations.';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $count = (new MigrationRunner($this->db, $this->logger))
            ->run([$this->app->basePath('database/Migrations')]);
        $out->success("Migrations executed: {$count}");

        return 0;
    }
}
