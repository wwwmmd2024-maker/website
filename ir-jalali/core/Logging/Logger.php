<?php

declare(strict_types=1);

namespace IRJalali\Core\Logging;

/**
 * Daily-rotated file logger with channels (app, security, database, api, ...).
 */
final class Logger
{
    private const LEVELS = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    private string $channel = 'app';

    public function __construct(
        private readonly string $directory,
        private readonly string $minLevel = 'info',
    ) {
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0755, true);
        }
    }

    public function channel(string $channel): self
    {
        $clone = clone $this;
        $clone->channel = preg_replace('/[^a-z0-9_-]/i', '', $channel) ?: 'app';

        return $clone;
    }

    public function debug(string $message, array $context = []): void
    {
        $this->write('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('error', $message, $context);
    }

    private function write(string $level, string $message, array $context): void
    {
        if ((self::LEVELS[$level] ?? 1) < (self::LEVELS[$this->minLevel] ?? 1)) {
            return;
        }
        $line = sprintf(
            "[%s] %s.%s: %s %s\n",
            date('Y-m-d H:i:s'),
            $this->channel,
            strtoupper($level),
            $message,
            $context !== [] ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );
        @file_put_contents(
            $this->directory . '/' . $this->channel . '-' . date('Y-m-d') . '.log',
            $line,
            FILE_APPEND | LOCK_EX
        );
    }

    /** @return list<string> newest-first log lines for the admin Log Viewer. */
    public function tail(string $channel, int $lines = 200): array
    {
        $channel = preg_replace('/[^a-z0-9_-]/i', '', $channel) ?: 'app';
        $files = glob($this->directory . '/' . $channel . '-*.log') ?: [];
        rsort($files);
        $out = [];
        foreach (array_slice($files, 0, 3) as $file) {
            $content = file($file, FILE_IGNORE_NEW_LINES);
            if ($content === false) {
                continue;
            }
            foreach (array_reverse($content) as $line) {
                $out[] = $line;
                if (count($out) >= $lines) {
                    break 2;
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    public function channels(): array
    {
        $files = glob($this->directory . '/*.log') ?: [];
        $channels = [];
        foreach ($files as $file) {
            $base = basename($file, '.log');
            $channel = (string) preg_replace('/-\d{4}-\d{2}-\d{2}$/', '', $base);
            $channels[$channel] = true;
        }
        $list = array_keys($channels);
        sort($list);

        return $list;
    }
}
