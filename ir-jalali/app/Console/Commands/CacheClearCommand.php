<?php

declare(strict_types=1);

namespace IRJalali\App\Console\Commands;

use IRJalali\Core\Cache\CacheInterface;
use IRJalali\Core\Console\Command;
use IRJalali\Core\Console\Output;

final class CacheClearCommand extends Command
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function name(): string
    {
        return 'cache:clear';
    }

    public function description(): string
    {
        return 'Flush the application cache.';
    }

    /** @param list<string> $args @param array<string, string|bool> $options */
    public function handle(array $args, array $options, Output $out): int
    {
        $this->cache->clear();
        $out->success('Cache cleared.');

        return 0;
    }
}
