<?php

declare(strict_types=1);

namespace IRJalali\Core\Builder;

/**
 * Server-side whitelist validation + sanitization for builder trees.
 * NOTHING reaches storage or the renderer without passing here.
 */
final class TreeValidator
{
    public const MAX_NODES = 1500;
    public const MAX_DEPTH = 12;

    /** Node type => [children allowed, content keys, category] */
    public const TYPES = [
        // Structural
        'section' => [true, [], 'layout'],
        'container' => [true, [], 'layout'],
        'grid' => [true, ['columns'], 'layout'],
        'columns' => [true, ['count'], 'layout'],
        'column' => [true, [], 'layout'],
        // Typography / content
        'heading' => [false, ['level', 'text'], 'typography'],
        'text' => [false, ['text'], 'basic'],
        'richtext' => [false, ['html'], 'content'],
        'button' => [false, ['text', 'url', 'target', 'variant', 'size'], 'basic'],
        'icon' => [false, ['name', 'size'], 'media'],
        'divider' => [false, [], 'basic'],
        'spacer' => [false, ['height'], 'layout'],
        // Media
        'image' => [false, ['src', 'alt', 'width', 'link', 'caption'], 'media'],
        'video' => [false, ['src', 'poster', 'autoplay', 'controls'], 'media'],
        'gallery' => [false, ['images', 'columns'], 'media'],
        'map' => [false, ['lat', 'lng', 'zoom', 'address'], 'advanced'],
        // Components
        'card' => [false, ['title', 'text', 'image', 'link', 'link_text'], 'content'],
        'tabs' => [false, ['items'], 'content'],
        'accordion' => [false, ['items'], 'content'],
        'faq' => [false, ['items'], 'content'],
        'carousel' => [false, ['slides', 'autoplay', 'interval'], 'content'],
        'slider' => [false, ['slides', 'autoplay', 'interval'], 'content'],
        'counter' => [false, ['number', 'suffix', 'label'], 'marketing'],
        'progress' => [false, ['percent', 'label'], 'marketing'],
        'pricing' => [false, ['plans'], 'marketing'],
        'testimonial' => [false, ['items'], 'marketing'],
        'team' => [false, ['members'], 'content'],
        'logogrid' => [false, ['images'], 'marketing'],
        // Dynamic / data
        'menu' => [false, ['location', 'menu_id'], 'theme'],
        'search' => [false, ['placeholder'], 'content'],
        'form' => [false, ['preset', 'form_id'], 'forms'],
        'postlist' => [false, ['query', 'layout'], 'dynamic'],
        'postgrid' => [false, ['query', 'layout'], 'dynamic'],
        'queryloop' => [true, ['query', 'item'], 'dynamic'],
        'dynamicfield' => [false, ['binding', 'fallback'], 'dynamic'],
        'dynamicimage' => [false, ['binding', 'alt'], 'dynamic'],
        'dynamiclink' => [false, ['binding', 'text'], 'dynamic'],
        // Extension points
        'block' => [false, ['block', 'data'], 'advanced'],
        'widget' => [false, ['widget', 'data'], 'advanced'],
        'html' => [false, ['code'], 'advanced'],
    ];

    /** CSS props the builder may persist (everything else is dropped). */
    public const STYLE_PROPS = [
        'color', 'background', 'backgroundGradient', 'fontSize', 'fontWeight',
        'textAlign', 'lineHeight', 'letterSpacing', 'width', 'maxWidth', 'height',
        'minHeight', 'marginTop', 'marginRight', 'marginBottom', 'marginLeft',
        'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
        'borderWidth', 'borderColor', 'borderStyle', 'borderRadius',
        'boxShadow', 'display', 'flexDirection', 'justifyContent', 'alignItems',
        'gap', 'gridColumns', 'position', 'top', 'right', 'bottom', 'left', 'zIndex',
        'opacity', 'overflow',
    ];

    public const BREAKPOINTS = ['desktop', 'laptop', 'tablet', 'mobile'];

    /** @return array{ok: bool, tree?: array, errors?: list<string>} */
    public function validate(mixed $tree): array
    {
        $errors = [];
        if (!is_array($tree)) {
            return ['ok' => false, 'errors' => ['Tree must be an object.']];
        }
        $count = 0;
        $clean = $this->cleanNode($tree, 0, $count, $errors);
        if ($clean === null) {
            return ['ok' => false, 'errors' => $errors === [] ? ['Invalid root node.'] : $errors];
        }

        return ['ok' => true, 'tree' => $clean];
    }

    /** @param array<string, mixed> $node @param list<string> $errors @return array<string, mixed>|null */
    private function cleanNode(array $node, int $depth, int &$count, array &$errors): ?array
    {
        $count++;
        if ($count > self::MAX_NODES) {
            $errors[] = 'Tree exceeds maximum node count.';

            return null;
        }
        if ($depth > self::MAX_DEPTH) {
            $errors[] = 'Tree exceeds maximum depth.';

            return null;
        }

        $type = $node['type'] ?? '';
        if (!is_string($type) || !isset(self::TYPES[$type])) {
            $errors[] = 'Unknown node type: ' . substr((string) $type, 0, 40);

            return null;
        }
        [$allowChildren, $contentKeys] = self::TYPES[$type];

        $id = $node['id'] ?? '';
        if (!is_string($id) || !preg_match('/^n_[a-zA-Z0-9]{4,24}$/', $id)) {
            $id = 'n_' . substr(bin2hex(random_bytes(8)), 0, 12);
        }

        $clean = [
            'id' => $id,
            'type' => $type,
            'content' => $this->cleanContent($type, (array) ($node['content'] ?? []), $contentKeys),
            'attrs' => $this->cleanAttrs((array) ($node['attrs'] ?? [])),
            'style' => $this->cleanStyle((array) ($node['style'] ?? [])),
            'responsive' => $this->cleanResponsive((array) ($node['responsive'] ?? [])),
            'animation' => $this->cleanAnimation((array) ($node['animation'] ?? [])),
            'visibility' => $this->cleanVisibility((array) ($node['visibility'] ?? [])),
            'conditions' => $this->cleanConditions((array) ($node['conditions'] ?? [])),
            'dynamic' => $this->cleanDynamicBindings((array) ($node['dynamic'] ?? [])),
            'locked' => !empty($node['locked']),
            'hidden' => !empty($node['hidden']),
            'name' => mb_substr((string) ($node['name'] ?? ''), 0, 60),
            'children' => [],
        ];

        $children = $node['children'] ?? [];
        if (is_array($children) && $children !== []) {
            if (!$allowChildren) {
                $errors[] = "Node type [{$type}] cannot have children.";
            } else {
                foreach (array_slice($children, 0, 200) as $child) {
                    if (!is_array($child)) {
                        continue;
                    }
                    $cleaned = $this->cleanNode($child, $depth + 1, $count, $errors);
                    if ($cleaned !== null) {
                        $clean['children'][] = $cleaned;
                    }
                }
            }
        }

        return $clean;
    }

    /** @param array<string, mixed> $content @param list<string> $allowed @return array<string, mixed> */
    private function cleanContent(string $type, array $content, array $allowed): array
    {
        $out = [];
        foreach ($allowed as $key) {
            if (!array_key_exists($key, $content)) {
                continue;
            }
            $out[$key] = $this->cleanValue($type, $key, $content[$key]);
        }

        return $out;
    }

    private function cleanValue(string $type, string $key, mixed $value): mixed
    {
        // URLs — strict scheme whitelist.
        if (in_array($key, ['url', 'src', 'poster', 'link', 'image'], true) && is_string($value)) {
            return $this->cleanUrl($value);
        }
        // Integers.
        if (in_array($key, ['level', 'columns', 'count', 'height', 'size', 'zoom', 'percent', 'number', 'interval', 'menu_id', 'form_id'], true)) {
            if (is_numeric($value)) {
                return (int) $value;
            }

            return 0;
        }
        // Floats.
        if (in_array($key, ['lat', 'lng'], true)) {
            return is_numeric($value) ? (float) $value : 0.0;
        }
        // Booleans.
        if (in_array($key, ['autoplay', 'controls'], true)) {
            return !empty($value);
        }
        // List structures (tabs/accordion/faq/slides/plans/testimonials/members/images/items).
        if (in_array($key, ['items', 'slides', 'plans', 'members', 'images'], true) && is_array($value)) {
            $out = [];
            foreach (array_slice($value, 0, 50) as $item) {
                if (is_string($item)) {
                    $out[] = $this->cleanUrl($item) !== '' || str_starts_with($item, 'data:')
                        ? mb_substr($item, 0, 2000)
                        : mb_substr($item, 0, 2000);
                    continue;
                }
                if (!is_array($item)) {
                    continue;
                }
                $cleanItem = [];
                foreach ($item as $ik => $iv) {
                    if (!is_string($ik) || !is_scalar($iv)) {
                        continue;
                    }
                    $ik = mb_substr($ik, 0, 30);
                    $cleanItem[$ik] = in_array($ik, ['url', 'src', 'image', 'link'], true)
                        ? $this->cleanUrl((string) $iv)
                        : mb_substr((string) $iv, 0, 2000);
                }
                $out[] = $cleanItem;
            }

            return $out;
        }
        // Query objects (validated deeply by QueryLoop).
        if ($key === 'query' && is_array($value)) {
            return QueryLoop::sanitizeQuery($value);
        }
        // Item template for query loop (a mini tree, validated recursively).
        if ($key === 'item' && is_array($value) && $type === 'queryloop') {
            $errors = [];
            $count = 0;
            $cleaned = $this->cleanNode($value, 1, $count, $errors);

            return $cleaned ?? ['id' => 'n_fallback', 'type' => 'text', 'content' => ['text' => '{{post.title}}'], 'children' => []];
        }
        // Block/widget references.
        if (in_array($key, ['block', 'widget', 'preset', 'binding', 'location', 'variant', 'size', 'target', 'layout'], true)) {
            return mb_substr((string) $value, 0, 120);
        }
        if ($key === 'data' && is_array($value)) {
            return $this->cleanScalarMap($value, 120, 2000);
        }
        // Free text (escaped at render).
        if (is_string($value)) {
            return mb_substr($value, 0, 20000);
        }
        if (is_scalar($value)) {
            return $value;
        }

        return null;
    }

    /** @param array<string, mixed> $map @return array<string, mixed> */
    private function cleanScalarMap(array $map, int $keyLen, int $valLen): array
    {
        $out = [];
        $i = 0;
        foreach ($map as $k => $v) {
            if ($i++ >= 100 || !is_string($k)) {
                break;
            }
            if (is_scalar($v)) {
                $out[mb_substr($k, 0, $keyLen)] = mb_substr((string) $v, 0, $valLen);
            } elseif (is_array($v)) {
                $out[mb_substr($k, 0, $keyLen)] = $this->cleanScalarMap($v, $keyLen, $valLen);
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $attrs @return array<string, mixed> */
    private function cleanAttrs(array $attrs): array
    {
        $out = [];
        if (isset($attrs['anchor']) && is_string($attrs['anchor'])) {
            $anchor = preg_replace('/[^a-zA-Z0-9\-_]/', '', $attrs['anchor']);
            if ($anchor !== '') {
                $out['anchor'] = substr($anchor, 0, 60);
            }
        }
        if (isset($attrs['cssClass']) && is_string($attrs['cssClass'])) {
            $classes = preg_split('/\s+/', trim($attrs['cssClass'])) ?: [];
            $safe = [];
            foreach (array_slice($classes, 0, 10) as $class) {
                $class = preg_replace('/[^a-zA-Z0-9\-_]/', '', $class);
                if ($class !== '') {
                    $safe[] = substr($class, 0, 40);
                }
            }
            if ($safe !== []) {
                $out['cssClass'] = implode(' ', $safe);
            }
        }
        if (isset($attrs['css']) && is_string($attrs['css'])) {
            // Custom CSS: strip dangerous constructs, scope at render.
            $css = mb_substr($attrs['css'], 0, 5000);
            $css = (string) preg_replace('/<\/?[^>]+>/', '', $css);
            $css = (string) preg_replace('/expression\s*\(|javascript\s*:|@import|behavior\s*:/i', '', $css);
            if (trim($css) !== '') {
                $out['css'] = $css;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $style @return array<string, string> */
    private function cleanStyle(array $style): array
    {
        $out = [];
        foreach (self::STYLE_PROPS as $prop) {
            if (!isset($style[$prop]) || !is_scalar($style[$prop])) {
                continue;
            }
            $value = trim(mb_substr((string) $style[$prop], 0, 200));
            if ($value === '' || str_contains($value, ';') || str_contains($value, '{') || str_contains($value, '}')) {
                continue;
            }
            // No url() / expression in style values (images use content.src).
            if (preg_match('/url\s*\(|expression\s*\(|javascript\s*:/i', $value)) {
                continue;
            }
            $out[$prop] = $value;
        }

        return $out;
    }

    /** @param array<string, mixed> $responsive @return array<string, array<string, string>> */
    private function cleanResponsive(array $responsive): array
    {
        $out = [];
        foreach (['laptop', 'tablet', 'mobile'] as $breakpoint) {
            if (!isset($responsive[$breakpoint]) || !is_array($responsive[$breakpoint])) {
                continue;
            }
            $cleaned = $this->cleanStyle($responsive[$breakpoint]);
            if ($cleaned !== []) {
                $out[$breakpoint] = $cleaned;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $animation @return array<string, mixed> */
    private function cleanAnimation(array $animation): array
    {
        $type = (string) ($animation['type'] ?? 'none');
        if (!in_array($type, ['none', 'fade', 'fade-up', 'fade-down', 'slide-right', 'slide-left', 'zoom', 'flip'], true)) {
            return [];
        }
        if ($type === 'none') {
            return [];
        }

        return [
            'type' => $type,
            'delay' => min(5000, max(0, (int) ($animation['delay'] ?? 0))),
            'duration' => min(3000, max(100, (int) ($animation['duration'] ?? 600))),
        ];
    }

    /** @param array<string, mixed> $visibility @return array<string, bool> */
    private function cleanVisibility(array $visibility): array
    {
        $out = [];
        foreach (['desktop', 'laptop', 'tablet', 'mobile'] as $device) {
            if (array_key_exists($device, $visibility)) {
                $out[$device] = !empty($visibility[$device]);
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> */
    private function cleanConditions(array $conditions): array
    {
        $out = [];
        foreach (array_slice($conditions, 0, 10) as $condition) {
            if (!is_array($condition)) {
                continue;
            }
            $rule = (string) ($condition['rule'] ?? '');
            if (!in_array($rule, ['logged_in', 'logged_out', 'user_role', 'device', 'post_type', 'date_range'], true)) {
                continue;
            }
            $clean = ['rule' => $rule];
            if (isset($condition['value'])) {
                $clean['value'] = is_array($condition['value'])
                    ? array_slice(array_map(fn($v) => mb_substr((string) $v, 0, 60), $condition['value']), 0, 10)
                    : mb_substr((string) $condition['value'], 0, 120);
            }

            $out[] = $clean;
        }

        return $out;
    }

    /** @param array<string, mixed> $bindings @return array<string, string> */
    private function cleanDynamicBindings(array $bindings): array
    {
        $out = [];
        foreach ($bindings as $field => $binding) {
            if (!is_string($field) || !is_string($binding)) {
                continue;
            }
            if (!preg_match('/^[a-z0-9_.]{1,60}$/i', $field)) {
                continue;
            }
            if (!DynamicData::isValidBinding($binding)) {
                continue;
            }
            $out[$field] = $binding;
        }

        return $out;
    }

    private function cleanUrl(string $url): string
    {
        $url = trim(mb_substr($url, 0, 2000));
        if ($url === '') {
            return '';
        }
        // Relative, anchor, http(s), mailto, tel, and storage paths only.
        if (preg_match('#^(/|#|mailto:|tel:)#i', $url)) {
            return $url;
        }
        if (preg_match('#^https?://#i', $url) && filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }

        return '';
    }
}
