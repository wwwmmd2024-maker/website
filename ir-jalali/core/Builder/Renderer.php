<?php

declare(strict_types=1);

namespace IRJalali\Core\Builder;

use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Blocks\BlockRegistry;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Security\Csrf;
use IRJalali\Core\Security\Sanitize;
use IRJalali\Core\Widgets\WidgetRegistry;

/**
 * Server-side builder renderer: validated tree → escaped HTML + scoped CSS.
 * This output is AUTHORITATIVE (frontend, preview and headless all use it).
 */
final class Renderer
{
    /** @var list<string> */
    private array $css = [];

    /** @var array<string, true> */
    private array $assets = [];

    public function __construct(
        private readonly ConditionEngine $conditions,
        private readonly DynamicData $dynamic,
        private readonly QueryLoop $query,
        private readonly BlockRegistry $blocks,
        private readonly WidgetRegistry $widgets,
        private readonly Database $db,
        private readonly Csrf $csrf,
        private readonly ?Auth $auth = null,
    ) {
    }

    /** @param array<string, mixed> $tree */
    public function render(array $tree, RenderContext $ctx): RenderedOutput
    {
        $this->css = [];
        $this->assets = [];
        $html = $this->renderNode($tree, $ctx);

        return new RenderedOutput($html, implode("\n", $this->css), array_keys($this->assets));
    }

    public static function detectDevice(string $userAgent): string
    {
        $ua = strtolower($userAgent);
        if (preg_match('/tablet|ipad|playbook|silk(?!.*mobile)/', $ua)) {
            return 'tablet';
        }
        if (preg_match('/mobile|iphone|ipod|android|blackberry|windows phone/', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /** @param array<string, mixed> $node */
    private function renderNode(array $node, RenderContext $ctx, int $depth = 0): string
    {
        if ($depth > 15) {
            return '';
        }
        $type = (string) ($node['type'] ?? '');
        if ($type === '' || !isset(TreeValidator::TYPES[$type])) {
            return '';
        }
        if (!empty($node['hidden']) && !$ctx->isPreview) {
            return '';
        }
        if (!$this->conditions->passes((array) ($node['conditions'] ?? []), $ctx)) {
            return '';
        }

        $content = $this->applyDynamic((array) ($node['content'] ?? []), (array) ($node['dynamic'] ?? []), $ctx);
        $inner = $this->renderInner($type, $content, $node, $ctx, $depth);

        $id = (string) ($node['id'] ?? '');
        $selector = $id !== '' ? '.ijn-' . $id : '';
        $this->collectCss($selector, (array) ($node['style'] ?? []), (array) ($node['responsive'] ?? []), (array) ($node['attrs'] ?? []));

        $classes = ['ij-node', 'ij-' . $type];
        if ($id !== '') {
            $classes[] = 'ijn-' . $id;
        }
        foreach (['desktop' => 'desktop', 'laptop' => 'laptop', 'tablet' => 'tablet', 'mobile' => 'mobile'] as $device => $suffix) {
            $visibility = (array) ($node['visibility'] ?? []);
            if (array_key_exists($device, $visibility) && empty($visibility[$device])) {
                $classes[] = 'ij-hide-' . $suffix;
            }
        }
        $animation = (array) ($node['animation'] ?? []);
        $animAttr = '';
        if (!empty($animation['type'])) {
            $classes[] = 'ij-anim';
            $classes[] = 'ij-anim-' . $animation['type'];
            $animAttr = ' data-ij-delay="' . (int) ($animation['delay'] ?? 0) . '" data-ij-duration="' . (int) ($animation['duration'] ?? 600) . '"';
        }
        $attrs = (array) ($node['attrs'] ?? []);
        if (!empty($attrs['cssClass'])) {
            $classes[] = (string) $attrs['cssClass'];
        }
        $anchor = !empty($attrs['anchor']) ? ' id="' . Sanitize::attr($attrs['anchor']) . '"' : '';

        // Preview hooks for the visual editor (click-select + drop targets).
        $previewAttr = '';
        if ($ctx->isPreview && $id !== '') {
            $previewAttr = ' data-ij-id="' . Sanitize::attr($id) . '" data-ij-type="' . Sanitize::attr($type) . '"';
        }

        // Semantic wrappers for a few types.
        if ($type === 'section') {
            return '<section' . $anchor . ' class="' . implode(' ', $classes) . '"' . $animAttr . $previewAttr . '>' . $inner . '</section>';
        }

        return '<div' . $anchor . ' class="' . implode(' ', $classes) . '"' . $animAttr . $previewAttr . '>' . $inner . '</div>';
    }

    /** @param array<string, mixed> $content @param array<string, string> $bindings @return array<string, mixed> */
    private function applyDynamic(array $content, array $bindings, RenderContext $ctx): array
    {
        foreach ($bindings as $field => $binding) {
            $value = $this->dynamic->resolveString($binding, $ctx);
            // Support nested paths like "content.text" or plain "text".
            $key = str_contains($field, '.') ? substr($field, strrpos($field, '.') + 1) : $field;
            $content[$key] = $value;
        }
        // Also resolve inline bindings inside text-ish fields.
        foreach (['text', 'html', 'title', 'excerpt', 'caption', 'fallback'] as $key) {
            if (isset($content[$key]) && is_string($content[$key]) && str_contains($content[$key], '{{')) {
                $content[$key] = $this->dynamic->resolveString($content[$key], $ctx);
            }
        }

        return $content;
    }

    /** @param array<string, mixed> $content @param array<string, mixed> $node */
    private function renderInner(string $type, array $content, array $node, RenderContext $ctx, int $depth): string
    {
        $children = '';
        foreach ((array) ($node['children'] ?? []) as $child) {
            if (is_array($child)) {
                $children .= $this->renderNode($child, $ctx, $depth + 1);
            }
        }

        return match ($type) {
            'section', 'container', 'column', 'columns', 'grid' => $children,
            'heading' => $this->heading($content),
            'text' => '<p class="ij-text">' . nl2br(Sanitize::html($content['text'] ?? '')) . '</p>',
            'richtext' => '<div class="ij-rich">' . Sanitize::richText($content['html'] ?? '') . '</div>',
            'button' => $this->button($content),
            'image' => $this->image($content),
            'video' => $this->video($content),
            'gallery' => $this->gallery($content),
            'icon' => $this->icon($content),
            'divider' => '<hr class="ij-divider">',
            'spacer' => '<div class="ij-spacer" style="height:' . max(0, (int) ($content['height'] ?? 40)) . 'px"></div>',
            'card' => $this->card($content),
            'tabs' => $this->tabs($content, (string) ($node['id'] ?? 'x')),
            'accordion', 'faq' => $this->accordion($content),
            'carousel', 'slider' => $this->carousel($content),
            'counter' => $this->counter($content),
            'progress' => $this->progress($content),
            'pricing' => $this->pricing($content),
            'testimonial' => $this->testimonial($content),
            'team' => $this->team($content),
            'logogrid' => $this->logogrid($content),
            'map' => $this->map($content),
            'form' => $this->form($content),
            'menu' => $this->menu($content),
            'search' => $this->widgets->render('search', ['placeholder' => $content['placeholder'] ?? 'جستجو...'], $ctx),
            'postlist' => $this->postList($content, 'list', $ctx),
            'postgrid' => $this->postList($content, 'grid', $ctx),
            'queryloop' => $this->queryLoop($content, $node, $ctx, $depth),
            'dynamicfield' => '<span class="ij-dynamic">' . Sanitize::html($this->dynamic->resolveString((string) ($content['binding'] ?? ''), $ctx) ?: (string) ($content['fallback'] ?? '')) . '</span>',
            'dynamicimage' => $this->dynamicImage($content, $ctx),
            'dynamiclink' => $this->dynamicLink($content, $ctx),
            'block' => $this->blocks->render((string) ($content['block'] ?? ''), (array) ($content['data'] ?? []), $ctx, fn(string $p) => $this->auth?->can($p) ?? false),
            'widget' => $this->widgets->render((string) ($content['widget'] ?? ''), (array) ($content['data'] ?? []), $ctx),
            'html' => Sanitize::richText($content['code'] ?? ''),
            default => '',
        };
    }

    /** @param array<string, mixed> $content */
    private function heading(array $content): string
    {
        $level = min(6, max(1, (int) ($content['level'] ?? 2)));

        return '<h' . $level . ' class="ij-heading">' . Sanitize::html($content['text'] ?? '') . '</h' . $level . '>';
    }

    /** @param array<string, mixed> $content */
    private function button(array $content): string
    {
        $variant = in_array($content['variant'] ?? '', ['primary', 'secondary', 'outline', 'ghost'], true) ? $content['variant'] : 'primary';
        $size = in_array($content['size'] ?? '', ['sm', 'md', 'lg'], true) ? $content['size'] : 'md';
        $target = ($content['target'] ?? '') === '_blank' ? ' target="_blank" rel="noopener"' : '';

        return '<a class="ij-btn ij-btn-' . $variant . ' ij-btn-' . $size . '" href="' . Sanitize::url((string) ($content['url'] ?? '#')) . '"' . $target . '>'
            . Sanitize::html($content['text'] ?? 'دکمه') . '</a>';
    }

    /** @param array<string, mixed> $content */
    private function image(array $content): string
    {
        $src = Sanitize::url((string) ($content['src'] ?? ''));
        if ($src === '') {
            return '';
        }
        $alt = Sanitize::attr($content['alt'] ?? '');
        $width = !empty($content['width']) ? ' width="' . max(1, (int) $content['width']) . '"' : '';
        $img = '<img src="' . $src . '" alt="' . $alt . '"' . $width . ' loading="lazy">';
        if (!empty($content['link'])) {
            $img = '<a href="' . Sanitize::url((string) $content['link']) . '">' . $img . '</a>';
        }
        $caption = !empty($content['caption']) ? '<figcaption>' . Sanitize::html($content['caption']) . '</figcaption>' : '';

        return '<figure class="ij-figure">' . $img . $caption . '</figure>';
    }

    /** @param array<string, mixed> $content */
    private function video(array $content): string
    {
        $src = trim((string) ($content['src'] ?? ''));
        if ($src === '') {
            return '';
        }
        // Whitelisted embed providers.
        if (preg_match('#^(https?://)?(www\.)?(youtube\.com/watch\?v=|youtu\.be/|vimeo\.com/|aparat\.com/v/)#i', $src)) {
            $embed = $this->toEmbedUrl($src);
            if ($embed !== '') {
                return '<div class="ij-video"><iframe src="' . Sanitize::url($embed) . '" loading="lazy" allowfullscreen title="video"></iframe></div>';
            }
        }
        if (!preg_match('#^(/|https?://)#i', $src)) {
            return '';
        }
        $poster = !empty($content['poster']) ? ' poster="' . Sanitize::url((string) $content['poster']) . '"' : '';
        $autoplay = !empty($content['autoplay']) ? ' autoplay muted loop playsinline' : '';
        $controls = empty($content['controls']) && empty($content['autoplay']) ? ' controls' : (!empty($content['controls']) ? ' controls' : '');

        return '<div class="ij-video"><video src="' . Sanitize::url($src) . '"' . $poster . $autoplay . $controls . '></video></div>';
    }

    private function toEmbedUrl(string $src): string
    {
        if (preg_match('#youtube\.com/watch\?v=([a-zA-Z0-9_\-]{6,20})#', $src, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('#youtu\.be/([a-zA-Z0-9_\-]{6,20})#', $src, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('#vimeo\.com/(\d{4,12})#', $src, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        if (preg_match('#aparat\.com/v/([a-zA-Z0-9]{3,20})#', $src, $m)) {
            return 'https://www.aparat.com/video/video/embed/videohash/' . $m[1] . '/vt/frame';
        }

        return '';
    }

    /** @param array<string, mixed> $content */
    private function gallery(array $content): string
    {
        $images = is_array($content['images'] ?? null) ? $content['images'] : [];
        if ($images === []) {
            return '';
        }
        $cols = min(6, max(1, (int) ($content['columns'] ?? 3)));
        $html = '<div class="ij-gallery ij-gallery-' . $cols . '">';
        foreach (array_slice($images, 0, 30) as $image) {
            $src = is_string($image) ? $image : (string) ($image['src'] ?? '');
            if (Sanitize::url($src) === '') {
                continue;
            }
            $alt = is_array($image) ? Sanitize::attr($image['alt'] ?? '') : '';
            $html .= '<a href="' . Sanitize::url($src) . '" target="_blank" rel="noopener"><img src="' . Sanitize::url($src) . '" alt="' . $alt . '" loading="lazy"></a>';
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $content */
    private function icon(array $content): string
    {
        $paths = [
            'check' => '<path d="m4 12.5 5 5L20 6.5"/>',
            'star' => '<path d="m12 2 3 6.6 7 .8-5.2 4.8 1.4 7-6.2-3.6L5.8 21l1.4-7L2 9.4l7-.8z"/>',
            'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
            'mail' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>',
            'pin' => '<path d="M12 21s7-5.5 7-11a7 7 0 1 0-14 0c0 5.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/>',
            'arrow' => '<path d="M19 12H5m6-6-6 6 6 6"/>',
            'plus' => '<path d="M12 5v14M5 12h14"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 5-6 8-6s6.5 2 8 6"/>',
            'cart' => '<circle cx="9" cy="20" r="1.5"/><circle cx="17" cy="20" r="1.5"/><path d="M2 3h3l2.5 12h11L21 7H6"/>',
            'heart' => '<path d="M12 20.5S2 15 2 8.5A4.5 4.5 0 0 1 12 6a4.5 4.5 0 0 1 10 2.5C22 15 12 20.5 12 20.5z"/>',
        ];
        $name = (string) ($content['name'] ?? 'star');
        $path = $paths[$name] ?? $paths['star'];
        $size = min(160, max(12, (int) ($content['size'] ?? 32)));

        return '<span class="ij-icon"><svg width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' . $path . '</svg></span>';
    }

    /** @param array<string, mixed> $content */
    private function card(array $content): string
    {
        $html = '<div class="ij-card">';
        if (!empty($content['image']) && Sanitize::url((string) $content['image']) !== '') {
            $html .= '<img src="' . Sanitize::url((string) $content['image']) . '" alt="" loading="lazy">';
        }
        $html .= '<div class="ij-card-body"><h4>' . Sanitize::html($content['title'] ?? '') . '</h4>';
        if (!empty($content['text'])) {
            $html .= '<p>' . Sanitize::html($content['text']) . '</p>';
        }
        if (!empty($content['link'])) {
            $html .= '<a href="' . Sanitize::url((string) $content['link']) . '">' . Sanitize::html($content['link_text'] ?? 'بیشتر') . '</a>';
        }

        return $html . '</div></div>';
    }

    /** @param array<string, mixed> $content */
    private function tabs(array $content, string $nodeId): string
    {
        $items = is_array($content['items'] ?? null) ? array_slice($content['items'], 0, 12) : [];
        if ($items === []) {
            return '';
        }
        $name = 'ijt-' . preg_replace('/[^a-zA-Z0-9]/', '', $nodeId);
        $html = '<div class="ij-tabs">';
        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                continue;
            }
            $checked = $i === 0 ? ' checked' : '';
            $html .= '<input type="radio" name="' . $name . '" id="' . $name . '-' . $i . '"' . $checked . '>'
                . '<label for="' . $name . '-' . $i . '">' . Sanitize::html($item['title'] ?? 'تب') . '</label>'
                . '<div class="ij-tabpanel">' . Sanitize::richText($item['content'] ?? '') . '</div>';
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $content */
    private function accordion(array $content): string
    {
        $items = is_array($content['items'] ?? null) ? array_slice($content['items'], 0, 30) : [];
        $html = '<div class="ij-accordion">';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $html .= '<details><summary>' . Sanitize::html($item['title'] ?? $item['question'] ?? 'مورد') . '</summary>'
                . '<div>' . Sanitize::richText($item['content'] ?? $item['answer'] ?? '') . '</div></details>';
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $content */
    private function carousel(array $content): string
    {
        $slides = is_array($content['slides'] ?? null) ? array_slice($content['slides'], 0, 20) : [];
        if ($slides === []) {
            return '';
        }
        $autoplay = !empty($content['autoplay']) ? ' data-autoplay="' . min(15000, max(1000, (int) ($content['interval'] ?? 4000))) . '"' : '';
        $html = '<div class="ij-carousel"' . $autoplay . '><div class="ij-track">';
        foreach ($slides as $slide) {
            if (!is_array($slide)) {
                continue;
            }
            $html .= '<div class="ij-slide">';
            if (!empty($slide['image']) && Sanitize::url((string) $slide['image']) !== '') {
                $html .= '<img src="' . Sanitize::url((string) $slide['image']) . '" alt="' . Sanitize::attr($slide['title'] ?? '') . '" loading="lazy">';
            }
            if (!empty($slide['title']) || !empty($slide['text'])) {
                $html .= '<div class="ij-slide-cap"><strong>' . Sanitize::html($slide['title'] ?? '') . '</strong>'
                    . (!empty($slide['text']) ? '<span>' . Sanitize::html($slide['text']) . '</span>' : '') . '</div>';
            }
            $html .= '</div>';
        }

        return $html . '</div><button class="ij-prev" type="button" aria-label="قبلی">‹</button><button class="ij-next" type="button" aria-label="بعدی">›</button></div>';
    }

    /** @param array<string, mixed> $content */
    private function counter(array $content): string
    {
        return '<div class="ij-counter"><span class="ij-num" data-count="' . (int) ($content['number'] ?? 0) . '">0</span>'
            . '<span class="ij-suffix">' . Sanitize::html($content['suffix'] ?? '') . '</span>'
            . '<span class="ij-label">' . Sanitize::html($content['label'] ?? '') . '</span></div>';
    }

    /** @param array<string, mixed> $content */
    private function progress(array $content): string
    {
        $percent = min(100, max(0, (int) ($content['percent'] ?? 0)));

        return '<div class="ij-progress"><span>' . Sanitize::html($content['label'] ?? '') . '</span>'
            . '<div class="ij-bar"><i style="width:' . $percent . '%"></i></div><b>' . $percent . '٪</b></div>';
    }

    /** @param array<string, mixed> $content */
    private function pricing(array $content): string
    {
        $plans = is_array($content['plans'] ?? null) ? array_slice($content['plans'], 0, 6) : [];
        $html = '<div class="ij-pricing">';
        foreach ($plans as $plan) {
            if (!is_array($plan)) {
                continue;
            }
            $features = '';
            foreach (explode("\n", (string) ($plan['features'] ?? '')) as $feature) {
                $feature = trim($feature);
                if ($feature !== '') {
                    $features .= '<li>' . Sanitize::html(mb_substr($feature, 0, 200)) . '</li>';
                }
            }
            $html .= '<div class="ij-plan' . (!empty($plan['featured']) ? ' ij-featured' : '') . '">'
                . '<h4>' . Sanitize::html($plan['name'] ?? '') . '</h4>'
                . '<div class="ij-price">' . Sanitize::html($plan['price'] ?? '') . '</div>'
                . '<ul>' . $features . '</ul>'
                . (!empty($plan['url']) ? '<a class="ij-btn ij-btn-primary" href="' . Sanitize::url((string) $plan['url']) . '">' . Sanitize::html($plan['cta'] ?? 'انتخاب') . '</a>' : '')
                . '</div>';
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $content */
    private function testimonial(array $content): string
    {
        $items = is_array($content['items'] ?? null) ? array_slice($content['items'], 0, 12) : [];
        $html = '<div class="ij-testimonials">';
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $html .= '<blockquote><p>«' . Sanitize::html($item['text'] ?? '') . '»</p><cite>— ' . Sanitize::html($item['name'] ?? '') . '</cite></blockquote>';
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $content */
    private function team(array $content): string
    {
        $members = is_array($content['members'] ?? null) ? array_slice($content['members'], 0, 12) : [];
        $html = '<div class="ij-team">';
        foreach ($members as $member) {
            if (!is_array($member)) {
                continue;
            }
            $html .= '<div class="ij-member">';
            if (!empty($member['image']) && Sanitize::url((string) $member['image']) !== '') {
                $html .= '<img src="' . Sanitize::url((string) $member['image']) . '" alt="' . Sanitize::attr($member['name'] ?? '') . '" loading="lazy">';
            }
            $html .= '<strong>' . Sanitize::html($member['name'] ?? '') . '</strong><span>' . Sanitize::html($member['role'] ?? '') . '</span></div>';
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $content */
    private function logogrid(array $content): string
    {
        $images = is_array($content['images'] ?? null) ? array_slice($content['images'], 0, 24) : [];
        $html = '<div class="ij-logos">';
        foreach ($images as $image) {
            $src = is_string($image) ? $image : (string) ($image['src'] ?? '');
            if (Sanitize::url($src) === '') {
                continue;
            }
            $html .= '<img src="' . Sanitize::url($src) . '" alt="logo" loading="lazy">';
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $content */
    private function map(array $content): string
    {
        $lat = (float) ($content['lat'] ?? 0);
        $lng = (float) ($content['lng'] ?? 0);
        if ($lat === 0.0 && $lng === 0.0) {
            $address = trim((string) ($content['address'] ?? ''));
            if ($address === '') {
                return '';
            }
            // Address fallback: link to OSM search (no geocoding key needed).
            return '<div class="ij-map"><a href="https://www.openstreetmap.org/search?query=' . urlencode($address) . '" target="_blank" rel="noopener">مشاهده روی نقشه: ' . Sanitize::html($address) . '</a></div>';
        }
        $zoom = min(19, max(1, (int) ($content['zoom'] ?? 14)));
        $delta = 0.02;
        $bbox = ($lng - $delta) . ',' . ($lat - $delta) . ',' . ($lng + $delta) . ',' . ($lat + $delta);
        $src = 'https://www.openstreetmap.org/export/embed.html?bbox=' . $bbox . '&layer=mapnik&marker=' . $lat . ',' . $lng;

        return '<div class="ij-map"><iframe src="' . $src . '" loading="lazy" title="map"></iframe></div>';
    }

    /** @param array<string, mixed> $content */
    private function form(array $content): string
    {
        $preset = (string) ($content['preset'] ?? 'contact');
        $formId = (int) ($content['form_id'] ?? 0);
        if ($formId > 0) {
            $form = $this->db->table('forms')->where('id', $formId)->where('is_active', 1)->first();
            if ($form === null) {
                return '';
            }
            $fields = $this->db->table('form_fields')->where('form_id', $formId)->orderBy('ordering')->get();
            $html = '<form class="ij-form" method="post" action="/forms/submit">' . $this->csrf->field()
                . '<input type="hidden" name="form_id" value="' . $formId . '">';
            foreach ($fields as $field) {
                $html .= $this->formField($field);
            }

            return $html . '<button type="submit">ارسال</button></form>';
        }
        if ($preset === 'newsletter') {
            return '<form class="ij-form" method="post" action="/newsletter/subscribe">' . $this->csrf->field()
                . '<label>ایمیل<input type="email" name="email" required dir="ltr"></label><button type="submit">عضویت در خبرنامه</button></form>';
        }

        // Contact preset (default).
        return '<form class="ij-form" method="post" action="/forms/submit">' . $this->csrf->field()
            . '<input type="hidden" name="preset" value="contact">'
            . '<label>نام<input type="text" name="name" required maxlength="100"></label>'
            . '<label>ایمیل<input type="email" name="email" required dir="ltr"></label>'
            . '<label>پیام<textarea name="message" rows="4" required maxlength="2000"></textarea></label>'
            . '<button type="submit">ارسال پیام</button></form>';
    }

    /** @param array<string, mixed> $field */
    private function formField(array $field): string
    {
        $key = preg_replace('/[^a-z0-9_\-]/i', '', (string) $field['key']);
        $label = Sanitize::html($field['label'] ?? $key);
        $settings = json_decode((string) ($field['settings'] ?? '{}'), true) ?: [];
        $required = !empty($settings['required']) ? ' required' : '';
        $type = (string) $field['type'];

        return match ($type) {
            'textarea' => '<label>' . $label . '<textarea name="fields[' . $key . ']" rows="4" maxlength="2000"' . $required . '></textarea></label>',
            'select' => $this->formSelect($key, $label, $settings, $required),
            'checkbox' => '<label class="ij-check"><input type="checkbox" name="fields[' . $key . ']" value="1"> ' . $label . '</label>',
            'email' => '<label>' . $label . '<input type="email" name="fields[' . $key . ']" dir="ltr" maxlength="191"' . $required . '></label>',
            'number' => '<label>' . $label . '<input type="number" name="fields[' . $key . ']"' . $required . '></label>',
            default => '<label>' . $label . '<input type="text" name="fields[' . $key . ']" maxlength="500"' . $required . '></label>',
        };
    }

    /** @param array<string, mixed> $settings */
    private function formSelect(string $key, string $label, array $settings, string $required): string
    {
        $html = '<label>' . $label . '<select name="fields[' . $key . ']"' . $required . '>';
        $options = is_array($settings['options'] ?? null) ? $settings['options'] : [];
        foreach (array_slice($options, 0, 50) as $option) {
            $html .= '<option>' . Sanitize::html(mb_substr((string) $option, 0, 150)) . '</option>';
        }

        return $html . '</select></label>';
    }

    /** @param array<string, mixed> $content */
    private function menu(array $content): string
    {
        $menu = null;
        if (!empty($content['menu_id'])) {
            $menu = $this->db->table('menus')->where('id', (int) $content['menu_id'])->first();
        } elseif (!empty($content['location'])) {
            $menu = $this->db->table('menus')->where('location', mb_substr((string) $content['location'], 0, 50))->orderBy('id')->first();
        }
        if ($menu === null) {
            return '';
        }
        $items = $this->db->table('menu_items')->where('menu_id', $menu['id'])->orderBy('ordering')->orderBy('id')->get();
        $tree = $this->nestMenu($items);

        return '<nav class="ij-menu" aria-label="' . Sanitize::attr($menu['name']) . '">' . $this->menuList($tree) . '</nav>';
    }

    /** @param list<array<string, mixed>> $items @return list<array<string, mixed>> */
    private function nestMenu(array $items): array
    {
        $byId = [];
        foreach ($items as $item) {
            $item['children'] = [];
            $byId[$item['id']] = $item;
        }
        $tree = [];
        foreach ($byId as $id => $item) {
            if (!empty($item['parent_id']) && isset($byId[$item['parent_id']])) {
                $byId[$item['parent_id']]['children'][] = &$byId[$id];
            } else {
                $tree[] = &$byId[$id];
            }
        }

        return $tree;
    }

    /** @param list<array<string, mixed>> $items */
    private function menuList(array $items): string
    {
        $html = '<ul>';
        foreach ($items as $item) {
            $url = Sanitize::url((string) ($item['url'] ?? '#'));
            $target = ($item['target'] ?? '') === '_blank' ? ' target="_blank" rel="noopener"' : '';
            $html .= '<li><a href="' . $url . '"' . $target . '>' . Sanitize::html($item['title']) . '</a>';
            if (!empty($item['children'])) {
                $html .= $this->menuList($item['children']);
            }
            $html .= '</li>';
        }

        return $html . '</ul>';
    }

    /** @param array<string, mixed> $content */
    private function postList(array $content, string $layout, RenderContext $ctx): string
    {
        $posts = $this->query->fetch((array) ($content['query'] ?? []));
        if ($posts === []) {
            return '<p class="ij-muted">مطلبی یافت نشد.</p>';
        }
        $html = '<div class="ij-posts ij-posts-' . $layout . '">';
        foreach ($posts as $post) {
            $url = '/' . ltrim((string) ($post['slug'] ?? ''), '/');
            $html .= '<article class="ij-postcard"><a href="' . Sanitize::url($url) . '">';
            if (!empty($post['featured_image'])) {
                $html .= '<img src="' . Sanitize::url((string) $post['featured_image']) . '" alt="" loading="lazy">';
            }
            $html .= '<h4>' . Sanitize::html($post['title'] ?? '') . '</h4>';
            if (!empty($post['excerpt'])) {
                $html .= '<p>' . Sanitize::html($post['excerpt']) . '</p>';
            }
            $html .= '</a></article>';
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $content @param array<string, mixed> $node */
    private function queryLoop(array $content, array $node, RenderContext $ctx, int $depth): string
    {
        $posts = $this->query->fetch((array) ($content['query'] ?? []));
        $item = (array) ($content['item'] ?? []);
        if ($posts === [] || $item === []) {
            return '<p class="ij-muted">مطلبی یافت نشد.</p>';
        }
        $html = '<div class="ij-loop">';
        foreach ($posts as $post) {
            $html .= '<div class="ij-loop-item">' . $this->renderNode($item, $ctx->withPost($post), $depth + 1) . '</div>';
        }

        return $html . '</div>';
    }

    /** @param array<string, mixed> $content */
    private function dynamicImage(array $content, RenderContext $ctx): string
    {
        $src = $this->dynamic->resolveString((string) ($content['binding'] ?? ''), $ctx);
        if (Sanitize::url($src) === '') {
            return '';
        }

        return '<img src="' . Sanitize::url($src) . '" alt="' . Sanitize::attr($content['alt'] ?? '') . '" loading="lazy">';
    }

    /** @param array<string, mixed> $content */
    private function dynamicLink(array $content, RenderContext $ctx): string
    {
        $url = $this->dynamic->resolveString((string) ($content['binding'] ?? ''), $ctx);

        return '<a href="' . Sanitize::url($url) . '">' . Sanitize::html($content['text'] ?? 'پیوند') . '</a>';
    }

    /**
     * @param array<string, string> $style
     * @param array<string, array<string, string>> $responsive
     * @param array<string, mixed> $attrs
     */
    private function collectCss(string $selector, array $style, array $responsive, array $attrs): void
    {
        if ($selector === '') {
            return;
        }
        $decls = $this->declarations($style);
        // Sensible structural defaults.
        if ($decls !== []) {
            $this->css[] = $selector . '{' . implode(';', $decls) . '}';
        }
        $breakpoints = ['laptop' => 1280, 'tablet' => 1024, 'mobile' => 768];
        foreach ($responsive as $breakpoint => $props) {
            if (!isset($breakpoints[$breakpoint]) || !is_array($props)) {
                continue;
            }
            $bpDecls = $this->declarations($props);
            if ($bpDecls !== []) {
                $this->css[] = '@media(max-width:' . $breakpoints[$breakpoint] . 'px){' . $selector . '{' . implode(';', $bpDecls) . '}}';
            }
        }
        if (!empty($attrs['css']) && is_string($attrs['css'])) {
            // Scope custom CSS: author writes declarations; we wrap in the node selector.
            $custom = trim($attrs['css']);
            if ($custom !== '') {
                $this->css[] = $selector . '{' . str_replace(['<', '>', '{', '}'], '', $custom) . '}';
            }
        }
    }

    /** @param array<string, string> $style @return list<string> */
    private function declarations(array $style): array
    {
        $map = [
            'color' => 'color', 'background' => 'background', 'backgroundGradient' => 'background',
            'fontSize' => 'font-size', 'fontWeight' => 'font-weight', 'textAlign' => 'text-align',
            'lineHeight' => 'line-height', 'letterSpacing' => 'letter-spacing', 'width' => 'width',
            'maxWidth' => 'max-width', 'height' => 'height', 'minHeight' => 'min-height',
            'marginTop' => 'margin-top', 'marginRight' => 'margin-right', 'marginBottom' => 'margin-bottom',
            'marginLeft' => 'margin-left', 'paddingTop' => 'padding-top', 'paddingRight' => 'padding-right',
            'paddingBottom' => 'padding-bottom', 'paddingLeft' => 'padding-left',
            'borderWidth' => 'border-width', 'borderColor' => 'border-color', 'borderStyle' => 'border-style',
            'borderRadius' => 'border-radius', 'boxShadow' => 'box-shadow', 'display' => 'display',
            'flexDirection' => 'flex-direction', 'justifyContent' => 'justify-content', 'alignItems' => 'align-items',
            'gap' => 'gap', 'position' => 'position', 'top' => 'top', 'right' => 'right',
            'bottom' => 'bottom', 'left' => 'left', 'zIndex' => 'z-index', 'opacity' => 'opacity', 'overflow' => 'overflow',
        ];
        $out = [];
        foreach ($style as $prop => $value) {
            if ($prop === 'gridColumns' && is_numeric($value)) {
                $out[] = 'grid-template-columns:repeat(' . min(12, max(1, (int) $value)) . ',1fr)';

                continue;
            }
            if (!isset($map[$prop]) || !is_string($value)) {
                continue;
            }
            $out[] = $map[$prop] . ':' . $value;
        }

        return $out;
    }
}
