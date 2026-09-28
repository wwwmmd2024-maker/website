<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

/**
 * Static security scan for uploaded plugin/theme packages.
 * Errors block installation; warnings are shown to the administrator.
 */
final class PluginSecurityScanner
{
    private const FORBIDDEN = [
        '/\beval\s*\(/i' => 'eval() is forbidden.',
        '/\bassert\s*\(/i' => 'assert() with string is forbidden.',
        '/\bshell_exec\s*\(/i' => 'shell_exec() is forbidden.',
        '/\bpassthru\s*\(/i' => 'passthru() is forbidden.',
        '/\bproc_open\s*\(/i' => 'proc_open() is forbidden.',
        '/\bpopen\s*\(/i' => 'popen() is forbidden.',
        '/\bsystem\s*\(/i' => 'system() is forbidden.',
        '/\bexec\s*\(/i' => 'exec() is forbidden.',
        '/`[^`]*`/' => 'Backtick shell execution is forbidden.',
        '/\bbase64_decode\s*\(/i' => 'base64_decode() needs review (often hides payloads).',
        '/\bgzinflate\s*\(/i' => 'gzinflate() needs review (often hides payloads).',
        '/\bstr_rot13\s*\(/i' => 'str_rot13() needs review (often hides payloads).',
        '/\bcreate_function\s*\(/i' => 'create_function() is forbidden.',
        '/preg_replace\s*\([^)]*\/[a-z]*e[a-z]*["\']/i' => 'preg_replace /e modifier is forbidden.',
        '/\$_GET\s*\[[^\]]+\]\s*\.\s*["\']?\s*(SELECT|INSERT|UPDATE|DELETE)/i' => 'Raw request data in SQL.',
    ];

    private const WARNINGS = [
        '/DROP\s+TABLE/i' => 'DROP TABLE needs review (legit in migration down()/uninstall).',
        '/\bfile_get_contents\s*\(\s*\$_(GET|POST|REQUEST)/i' => 'Remote fetch from request input.',
        '/\bcurl_/i' => 'Direct cURL usage — prefer the HttpClient broker.',
        '/\bfile_put_contents\s*\(/' => 'File writes — must stay inside the package directory.',
        '/\bunlink\s*\(/' => 'File deletion — reviewed at runtime by capability.',
        '/\bini_set\s*\(/' => 'Runtime INI changes.',
    ];

    /**
     * @return array{errors: list<string>, warnings: list<string>, files: int}
     */
    public function scan(string $directory, int $maxFiles = 2000, int $maxBytes = 50 * 1024 * 1024): array
    {
        $errors = [];
        $warnings = [];
        $files = 0;
        $bytes = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if ($file->isLink()) {
                $errors[] = 'Symlinks are not allowed: ' . $this->relative($directory, $file->getPathname());

                continue;
            }
            if (!$file->isFile()) {
                continue;
            }
            $files++;
            $bytes += $file->getSize();
            if ($files > $maxFiles) {
                $errors[] = 'Package exceeds maximum file count.';

                break;
            }
            if ($bytes > $maxBytes) {
                $errors[] = 'Package exceeds maximum size.';

                break;
            }
            $relative = $this->relative($directory, $file->getPathname());
            if (str_contains($relative, '..')) {
                $errors[] = 'Invalid path: ' . $relative;

                continue;
            }
            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            if ($file->getSize() > 1024 * 1024) {
                continue; // Skip huge generated files for pattern scan (still linted).
            }
            $code = @file_get_contents($file->getPathname());
            if (!is_string($code)) {
                continue;
            }
            // Token-strip strings/comments so SQL quoting ('`col`') and prose
            // never trip the patterns; real calls live outside strings.
            $code = $this->stripStringsAndComments($code);
            foreach (self::FORBIDDEN as $pattern => $message) {
                if (preg_match($pattern, $code)) {
                    $errors[] = $relative . ': ' . $message;
                }
            }
            foreach (self::WARNINGS as $pattern => $message) {
                if (preg_match($pattern, $code)) {
                    $warnings[] = $relative . ': ' . $message;
                }
            }
        }

        return ['errors' => array_slice(array_unique($errors), 0, 50), 'warnings' => array_slice(array_unique($warnings), 0, 50), 'files' => $files];
    }

    private function stripStringsAndComments(string $code): string
    {
        $out = '';
        foreach (@token_get_all($code) ?: [] as $token) {
            if (is_array($token)) {
                [$id, $text] = $token;
                if (in_array($id, [T_CONSTANT_ENCAPSED_STRING, T_COMMENT, T_DOC_COMMENT, T_ENCAPSED_AND_WHITESPACE], true)) {
                    $out .= str_repeat(' ', strlen($text));
                    continue;
                }
                $out .= $text;
            } else {
                $out .= $token;
            }
        }

        return $out;
    }

    /**
     * Lint every PHP file with the real interpreter. Returns failing files.
     *
     * @return list<string>
     */
    public function lint(string $directory): array
    {
        // Lint needs a working PHP CLI reachable through exec(). On hardened
        // shared hosts (exec disabled) or non-CLI SAPIs we skip lint instead
        // of producing false positives — the pattern scan still runs.
        if (!$this->cliAvailable()) {
            return [];
        }

        $bad = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            /** @var \SplFileInfo $file */
            if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $output = [];
            $code = 0;
            @exec(PHP_BINARY . ' -l ' . escapeshellarg($file->getPathname()) . ' 2>&1', $output, $code);
            if ($code !== 0) {
                $bad[] = $this->relative($directory, $file->getPathname()) . ': ' . implode(' ', array_slice($output, 0, 2));
            }
            if (count($bad) >= 20) {
                break;
            }
        }

        return $bad;
    }

    /** True when exec() works and PHP_BINARY behaves like a real CLI. */
    private function cliAvailable(): bool
    {
        static $available = null;
        if ($available !== null) {
            return $available;
        }
        $available = false;
        if (!function_exists('exec') || PHP_BINARY === '' || !is_executable(PHP_BINARY)) {
            return $available;
        }
        $output = [];
        $code = 0;
        @exec(PHP_BINARY . ' -v 2>&1', $output, $code);
        // A genuine CLI reports its version; anything else (wrapper printing
        // usage, disabled exec, etc.) means we cannot trust `php -l` output.
        $available = $code === 0 && preg_match('/PHP\s+\d+\.\d+/i', implode("\n", $output)) === 1;

        return $available;
    }

    private function relative(string $directory, string $path): string
    {
        return ltrim(substr($path, strlen($directory)), '/');
    }
}
