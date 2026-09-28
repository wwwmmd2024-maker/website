<?php

declare(strict_types=1);

namespace IRJalali\Core\Plugins;

/**
 * SSRF-guarded HTTP client for marketplace downloads and plugins
 * holding the network.request capability.
 */
final class HttpClient
{
    public function __construct(
        private readonly int $timeout = 25,
        private readonly int $maxBytes = 100 * 1024 * 1024,
    ) {
    }

    /** @return array{ok: bool, status?: int, body?: string, error?: string} */
    public function get(string $url): array
    {
        $check = $this->guardUrl($url);
        if (!$check['ok']) {
            return $check;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeout,
                'max_redirects' => 3,
                'header' => "User-Agent: IR-Jalali/1.1\r\n",
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $context, 0, $this->maxBytes + 1);
        if ($body === false) {
            return ['ok' => false, 'error' => 'Download failed or timed out.'];
        }
        if (strlen($body) > $this->maxBytes) {
            return ['ok' => false, 'error' => 'Response too large.'];
        }
        // Response headers: prefer the PHP 8.4+ API; on 8.2/8.3 fall back to
        // the magic local variable set by file_get_contents(). The ternary only
        // evaluates the branch it needs, so no deprecation is triggered.
        $headers = function_exists('http_get_last_response_headers')
            ? http_get_last_response_headers()
            : ($http_response_header ?? []);

        $status = 0;
        foreach (is_array($headers) ? $headers : [] as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($status >= 400 || $status === 0) {
            return ['ok' => false, 'error' => 'Remote server returned HTTP ' . $status . '.'];
        }

        return ['ok' => true, 'status' => $status, 'body' => $body];
    }

    /** Download a remote file to a local path (size-checked). */
    public function download(string $url, string $destPath): array
    {
        $result = $this->get($url);
        if (!$result['ok']) {
            return $result;
        }
        if (file_put_contents($destPath, $result['body']) === false) {
            return ['ok' => false, 'error' => 'Cannot write download to disk.'];
        }

        return ['ok' => true, 'status' => $result['status'], 'path' => $destPath];
    }

    /** @return array{ok: bool, error?: string} */
    private function guardUrl(string $url): array
    {
        $parts = parse_url($url);
        if ($parts === false || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return ['ok' => false, 'error' => 'Only http(s) URLs are allowed.'];
        }
        $host = strtolower($parts['host'] ?? '');
        if ($host === '') {
            return ['ok' => false, 'error' => 'Invalid URL.'];
        }
        // Block literal private IPs and localhost names outright.
        if (in_array($host, ['localhost', 'localhost.localdomain', '[::1]'], true)
            || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return ['ok' => false, 'error' => 'URL host is not allowed (SSRF protection).'];
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return ['ok' => false, 'error' => 'URL host is not allowed (SSRF protection).'];
            }

            return ['ok' => true];
        }
        // Resolve DNS and check every record (TOCTOU accepted for M2; documented).
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ($records === false || $records === []) {
            // Let it fail naturally at fetch time if DNS is blocked; do not allow blindly.
            return ['ok' => false, 'error' => 'Cannot resolve host.'];
        }
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? '';
            if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return ['ok' => false, 'error' => 'URL host resolves to a private address (SSRF protection).'];
            }
        }

        return ['ok' => true];
    }
}
