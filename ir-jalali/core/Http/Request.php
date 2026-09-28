<?php

declare(strict_types=1);

namespace IRJalali\Core\Http;

/**
 * Immutable-ish HTTP request wrapper.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $routeParams = [];

    /** @var array<string, mixed> */
    private array $attributes = [];

    private ?array $jsonBody = null;
    private bool $jsonParsed = false;

    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $post,
        private readonly array $server,
        private readonly array $cookies,
        private readonly array $files,
    ) {
    }

    public static function capture(): self
    {
        $server = $_SERVER;
        $uri = $server['REQUEST_URI'] ?? '/';
        $path = (string) parse_url($uri, PHP_URL_PATH);
        if ($path === '' || $path[0] !== '/') {
            $path = '/';
        }

        $method = strtoupper($server['REQUEST_METHOD'] ?? 'GET');
        // Method spoofing for HTML forms.
        if ($method === 'POST' && isset($_POST['_method'])) {
            $spoof = strtoupper((string) $_POST['_method']);
            if (in_array($spoof, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $spoof;
            }
        }

        return new self($method, $path, $_GET, $_POST, $server, $_COOKIE, $_FILES);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function isMethod(string ...$methods): bool
    {
        foreach ($methods as $m) {
            if ($this->method === strtoupper($m)) {
                return true;
            }
        }

        return false;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }
        if (array_key_exists($key, $this->query)) {
            return $this->query[$key];
        }
        $json = $this->json();
        if (array_key_exists($key, $json)) {
            return $json[$key];
        }

        return $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->query, $this->post, $this->json());
    }

    /** @return array<string, mixed> */
    public function only(string ...$keys): array
    {
        $all = $this->all();
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $all[$key] ?? null;
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function json(): array
    {
        if (!$this->jsonParsed) {
            $this->jsonParsed = true;
            $type = $this->header('Content-Type', '');
            if (str_contains($type, 'application/json')) {
                $raw = (string) file_get_contents('php://input');
                $decoded = json_decode($raw, true);
                $this->jsonBody = is_array($decoded) ? $decoded : [];
            } else {
                $this->jsonBody = [];
            }
        }

        return $this->jsonBody ?? [];
    }

    public function header(string $name, string $default = ''): string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($this->server[$key])) {
            return (string) $this->server[$key];
        }
        $special = ['CONTENT_TYPE' => 'Content-Type', 'CONTENT_LENGTH' => 'Content-Length'];
        foreach ($special as $sKey => $sName) {
            if (strcasecmp($name, $sName) === 0 && isset($this->server[$sKey])) {
                return (string) $this->server[$sKey];
            }
        }

        return $default;
    }

    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');
        if (str_starts_with($header, 'Bearer ')) {
            $token = trim(substr($header, 7));

            return $token !== '' ? $token : null;
        }

        return null;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With')) === 'xmlhttprequest';
    }

    public function wantsJson(): bool
    {
        return $this->isAjax() || str_contains($this->header('Accept'), 'application/json');
    }

    /** @return array<string, mixed>|null normalized $_FILES entry */
    public function file(string $key): ?array
    {
        if (!isset($this->files[$key]) || !is_array($this->files[$key])) {
            return null;
        }
        $file = $this->files[$key];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        return $file;
    }

    /** @return list<array<string, mixed>> */
    public function files(string $key): array
    {
        if (!isset($this->files[$key])) {
            return [];
        }
        $entry = $this->files[$key];
        if (!is_array($entry['name'] ?? null)) {
            $single = $this->file($key);

            return $single !== null ? [$single] : [];
        }
        $out = [];
        $count = count($entry['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($entry['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                continue;
            }
            $out[] = [
                'name' => $entry['name'][$i],
                'type' => $entry['type'][$i],
                'tmp_name' => $entry['tmp_name'][$i],
                'error' => $entry['error'][$i],
                'size' => $entry['size'][$i],
            ];
        }

        return $out;
    }

    public function setRouteParam(string $key, mixed $value): void
    {
        $this->routeParams[$key] = $value;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
