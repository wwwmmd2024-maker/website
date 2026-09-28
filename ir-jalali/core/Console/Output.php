<?php

declare(strict_types=1);

namespace IRJalali\Core\Console;

/**
 * CLI output + input helper (tty-aware colors, tables, prompts).
 */
final class Output
{
    private bool $colors;

    public function __construct()
    {
        $this->colors = (getenv('NO_COLOR') === false)
            && function_exists('posix_isatty') && @posix_isatty(STDOUT);
    }

    public function writeln(string $text = ''): void
    {
        fwrite(STDOUT, $text . PHP_EOL);
    }

    public function info(string $text): void
    {
        $this->writeln($this->paint($text, '36'));
    }

    public function success(string $text): void
    {
        $this->writeln($this->paint('✔ ' . $text, '32'));
    }

    public function warning(string $text): void
    {
        $this->writeln($this->paint('! ' . $text, '33'));
    }

    public function error(string $text): void
    {
        fwrite(STDERR, $this->paint('✘ ' . $text, '31') . PHP_EOL);
    }

    /**
     * @param list<string> $headers
     * @param list<list<string>> $rows
     */
    public function table(array $headers, array $rows): void
    {
        $widths = [];
        foreach ($headers as $i => $header) {
            $widths[$i] = $this->width($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i] ?? 0, $this->width((string) $cell));
            }
        }
        $line = function (array $cells) use ($widths): string {
            $out = [];
            foreach ($cells as $i => $cell) {
                $out[] = $this->pad((string) $cell, $widths[$i] ?? 0);
            }

            return '| ' . implode(' | ', $out) . ' |';
        };
        $sep = '+-' . implode('-+-', array_map(fn (int $w): string => str_repeat('-', $w), $widths)) . '-+';
        $this->writeln($sep);
        $this->writeln($line($headers));
        $this->writeln($sep);
        foreach ($rows as $row) {
            $this->writeln($line($row));
        }
        $this->writeln($sep);
    }

    public function ask(string $question, string $default = ''): string
    {
        $suffix = $default !== '' ? " [{$default}]" : '';
        fwrite(STDOUT, $question . $suffix . ': ');
        $answer = fgets(STDIN);
        if ($answer === false) {
            return $default;
        }
        $answer = trim($answer);

        return $answer === '' ? $default : $answer;
    }

    public function secret(string $question): string
    {
        fwrite(STDOUT, $question . ': ');
        $stty = trim((string) shell_exec('stty -g 2>/dev/null'));
        if ($stty !== '') {
            shell_exec('stty -echo 2>/dev/null');
        }
        $answer = fgets(STDIN);
        if ($stty !== '') {
            shell_exec('stty ' . escapeshellarg($stty) . ' 2>/dev/null');
        }
        fwrite(STDOUT, PHP_EOL);

        return $answer === false ? '' : trim($answer);
    }

    public function confirm(string $question, bool $default = false): bool
    {
        $answer = strtolower($this->ask($question, $default ? 'yes' : 'no'));
        if (in_array($answer, ['y', 'yes', '1', 'بله'], true)) {
            return true;
        }
        if (in_array($answer, ['n', 'no', '0', 'خیر'], true)) {
            return false;
        }

        return $default;
    }

    private function paint(string $text, string $color): string
    {
        return $this->colors ? "\033[{$color}m{$text}\033[0m" : $text;
    }

    private function width(string $text): int
    {
        return function_exists('mb_strwidth') ? mb_strwidth($text) : strlen($text);
    }

    private function pad(string $text, int $width): string
    {
        $pad = $width - $this->width($text);

        return $text . ($pad > 0 ? str_repeat(' ', $pad) : '');
    }
}
