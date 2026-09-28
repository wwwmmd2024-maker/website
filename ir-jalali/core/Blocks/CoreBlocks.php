<?php

declare(strict_types=1);

namespace IRJalali\Core\Blocks;

use IRJalali\Core\Builder\RenderContext;
use IRJalali\Core\Security\Sanitize;

/**
 * Core blocks shipped with the platform. Registered at boot.
 */
final class CoreBlocks
{
    public static function register(BlockRegistry $registry): void
    {
        $registry->register(new BlockDefinition(
            slug: 'core/alert',
            title: 'هشدار',
            category: 'content',
            icon: '⚠',
            description: 'جعبه پیام اطلاع‌رسانی',
            schema: [
                ['key' => 'text', 'type' => 'textarea', 'label' => 'متن'],
                ['key' => 'variant', 'type' => 'select', 'label' => 'نوع', 'options' => ['info' => 'اطلاع', 'success' => 'موفق', 'warning' => 'هشدار', 'danger' => 'خطا']],
            ],
            defaults: ['text' => 'متن هشدار', 'variant' => 'info'],
            render: function (array $data, RenderContext $ctx): string {
                $variant = in_array($data['variant'] ?? '', ['info', 'success', 'warning', 'danger'], true) ? $data['variant'] : 'info';

                return '<div class="ij-alert ij-alert-' . $variant . '" role="alert">' . Sanitize::html($data['text'] ?? '') . '</div>';
            },
            supports: ['spacing'],
        ));

        $registry->register(new BlockDefinition(
            slug: 'core/cta',
            title: 'دعوت به اقدام (CTA)',
            category: 'marketing',
            icon: '📣',
            description: 'بنر CTA با دکمه',
            schema: [
                ['key' => 'title', 'type' => 'text', 'label' => 'عنوان'],
                ['key' => 'text', 'type' => 'textarea', 'label' => 'توضیح'],
                ['key' => 'button_text', 'type' => 'text', 'label' => 'متن دکمه'],
                ['key' => 'button_url', 'type' => 'url', 'label' => 'پیوند دکمه'],
            ],
            defaults: ['title' => 'همین حالا شروع کنید', 'text' => '', 'button_text' => 'شروع', 'button_url' => '/contact'],
            render: function (array $data, RenderContext $ctx): string {
                $url = Sanitize::url((string) ($data['button_url'] ?? '#'));

                return '<div class="ij-cta"><h3>' . Sanitize::html($data['title'] ?? '') . '</h3>'
                    . ($data['text'] !== '' ? '<p>' . Sanitize::html($data['text']) . '</p>' : '')
                    . '<a class="ij-btn" href="' . $url . '">' . Sanitize::html($data['button_text'] ?? 'بیشتر') . '</a></div>';
            },
            supports: ['spacing', 'align'],
        ));

        $registry->register(new BlockDefinition(
            slug: 'core/breadcrumb',
            title: 'مسیرنما (Breadcrumb)',
            category: 'theme',
            icon: '🧭',
            description: 'مسیر صفحه جاری',
            schema: [
                ['key' => 'home_label', 'type' => 'text', 'label' => 'عنوان خانه'],
            ],
            defaults: ['home_label' => 'خانه'],
            render: function (array $data, RenderContext $ctx): string {
                $home = Sanitize::html($data['home_label'] ?? 'خانه');
                $html = '<nav class="ij-breadcrumb" aria-label="breadcrumb"><a href="/">' . $home . '</a>';
                if ($ctx->post !== null && !empty($ctx->post['title'])) {
                    $html .= '<span class="ij-sep">‹</span><span>' . Sanitize::html($ctx->post['title']) . '</span>';
                }
                $html .= '</nav>';

                return $html;
            },
        ));

        $registry->register(new BlockDefinition(
            slug: 'core/toc',
            title: 'فهرست مطالب',
            category: 'content',
            icon: '📑',
            description: 'فهرست خودکار سرفصل‌های نوشته جاری',
            schema: [
                ['key' => 'title', 'type' => 'text', 'label' => 'عنوان فهرست'],
                ['key' => 'depth', 'type' => 'select', 'label' => 'عمق', 'options' => ['2' => 'سطح ۲', '3' => 'سطح ۲ و ۳']],
            ],
            defaults: ['title' => 'فهرست مطالب', 'depth' => '2'],
            render: function (array $data, RenderContext $ctx): string {
                $content = $ctx->post !== null ? (string) ($ctx->post['content'] ?? '') : '';
                $max = ($data['depth'] ?? '2') === '3' ? 3 : 2;
                preg_match_all('/<h([23])[^>]*>(.*?)<\/h\1>/is', $content, $matches, PREG_SET_ORDER);
                $items = '';
                foreach ($matches as $i => $match) {
                    if ((int) $match[1] > $max) {
                        continue;
                    }
                    $text = trim(strip_tags($match[2]));
                    if ($text === '') {
                        continue;
                    }
                    $items .= '<li class="ij-toc-h' . $match[1] . '"><a href="#ij-toc-' . $i . '">' . Sanitize::html(mb_substr($text, 0, 120)) . '</a></li>';
                }
                if ($items === '') {
                    return '';
                }

                return '<div class="ij-toc"><strong>' . Sanitize::html($data['title'] ?? '') . '</strong><ul>' . $items . '</ul></div>';
            },
        ));
    }
}
