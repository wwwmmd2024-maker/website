<?php

declare(strict_types=1);

namespace IRJalali\Core\Widgets;

use IRJalali\Core\Builder\RenderContext;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Security\Csrf;
use IRJalali\Core\Security\Sanitize;

/**
 * Core widgets shipped with the platform.
 */
final class CoreWidgets
{
    public static function register(WidgetRegistry $registry, Database $db, Csrf $csrf): void
    {
        $registry->register(new WidgetDefinition(
            slug: 'search',
            title: 'جستجو',
            description: 'فرم جستجوی سایت',
            icon: '🔎',
            schema: [
                ['key' => 'placeholder', 'type' => 'text', 'label' => 'متن راهنما'],
            ],
            defaults: ['placeholder' => 'جستجو...'],
            render: fn(array $data, RenderContext $ctx): string =>
                '<form class="ij-search" method="get" action="/search" role="search">'
                . '<input type="search" name="q" placeholder="' . Sanitize::attr($data['placeholder'] ?? 'جستجو...') . '" required>'
                . '<button type="submit">جستجو</button></form>',
        ));

        $registry->register(new WidgetDefinition(
            slug: 'recent-posts',
            title: 'نوشته‌های تازه',
            description: 'فهرست آخرین نوشته‌ها',
            icon: '📰',
            schema: [
                ['key' => 'count', 'type' => 'number', 'label' => 'تعداد'],
                ['key' => 'post_type', 'type' => 'text', 'label' => 'نوع نوشته'],
            ],
            defaults: ['count' => 5, 'post_type' => 'post'],
            render: function (array $data, RenderContext $ctx) use ($db): string {
                $type = preg_match('/^[a-z0-9_\-]{1,40}$/i', (string) ($data['post_type'] ?? 'post')) ? (string) $data['post_type'] : 'post';
                $count = min(15, max(1, (int) ($data['count'] ?? 5)));
                $rows = $db->table('posts')
                    ->where('post_type', $type)
                    ->where('status', 'published')
                    ->whereNull('deleted_at')
                    ->orderBy('published_at', 'DESC')
                    ->limit($count)
                    ->get();
                if ($rows === []) {
                    return '<p class="ij-muted">نوشته‌ای وجود ندارد.</p>';
                }
                $html = '<ul class="ij-recent">';
                foreach ($rows as $row) {
                    $html .= '<li><a href="/' . Sanitize::attr($row['slug']) . '">' . Sanitize::html($row['title']) . '</a></li>';
                }

                return $html . '</ul>';
            },
        ));

        $registry->register(new WidgetDefinition(
            slug: 'categories',
            title: 'دسته‌بندی‌ها',
            description: 'فهرست دسته‌ها با شمارش',
            icon: '📂',
            schema: [
                ['key' => 'taxonomy', 'type' => 'text', 'label' => 'تاکسونومی'],
            ],
            defaults: ['taxonomy' => 'category'],
            render: function (array $data, RenderContext $ctx) use ($db): string {
                $taxonomy = preg_match('/^[a-z0-9_\-]{1,40}$/i', (string) ($data['taxonomy'] ?? 'category')) ? (string) $data['taxonomy'] : 'category';
                $tax = $db->table('taxonomies')->where('slug', $taxonomy)->first();
                if ($tax === null) {
                    return '<p class="ij-muted">دسته‌بندی یافت نشد.</p>';
                }
                $terms = $db->table('terms')->where('taxonomy_id', $tax['id'])->orderBy('name')->limit(30)->get();
                if ($terms === []) {
                    return '<p class="ij-muted">موردی وجود ندارد.</p>';
                }
                $html = '<ul class="ij-cats">';
                foreach ($terms as $term) {
                    $html .= '<li><a href="/search?taxonomy=' . Sanitize::attr($taxonomy) . '&term=' . Sanitize::attr($term['slug']) . '">'
                        . Sanitize::html($term['name']) . ' <span>(' . (int) $term['count'] . ')</span></a></li>';
                }

                return $html . '</ul>';
            },
        ));

        $registry->register(new WidgetDefinition(
            slug: 'login',
            title: 'ورود کاربر',
            description: 'فرم ورود یا خوش‌آمدگویی',
            icon: '🔑',
            render: function (array $data, RenderContext $ctx) use ($csrf): string {
                if ($ctx->user !== null) {
                    $name = Sanitize::html($ctx->user['display_name'] ?? $ctx->user['username'] ?? '');

                    return '<div class="ij-loginbox"><p>سلام، <b>' . $name . '</b> 👋</p>'
                        . '<p><a href="/admin">پیشخوان</a> · '
                        . '<form method="post" action="/admin/logout" style="display:inline">' . $csrf->field() . '<button class="link" type="submit">خروج</button></form></p></div>';
                }

                return '<form class="ij-loginbox" method="post" action="/admin/login">'
                    . $csrf->field()
                    . '<label>نام کاربری<input type="text" name="login" required autocomplete="username"></label>'
                    . '<label>گذرواژه<input type="password" name="password" required autocomplete="current-password"></label>'
                    . '<button type="submit">ورود</button></form>';
            },
        ));

        $registry->register(new WidgetDefinition(
            slug: 'social',
            title: 'شبکه‌های اجتماعی',
            description: 'پیوندهای اجتماعی',
            icon: '🌐',
            schema: [
                ['key' => 'telegram', 'type' => 'url', 'label' => 'تلگرام'],
                ['key' => 'instagram', 'type' => 'url', 'label' => 'اینستاگرام'],
                ['key' => 'x', 'type' => 'url', 'label' => 'ایکس'],
                ['key' => 'linkedin', 'type' => 'url', 'label' => 'لینکدین'],
            ],
            render: function (array $data, RenderContext $ctx): string {
                $labels = ['telegram' => 'تلگرام', 'instagram' => 'اینستاگرام', 'x' => 'ایکس', 'linkedin' => 'لینکدین'];
                $html = '<div class="ij-social">';
                foreach ($labels as $key => $label) {
                    $url = trim((string) ($data[$key] ?? ''));
                    if ($url === '' || (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://'))) {
                        continue;
                    }
                    $html .= '<a href="' . Sanitize::url($url) . '" target="_blank" rel="noopener">' . $label . '</a>';
                }

                return $html . '</div>';
            },
        ));

        $registry->register(new WidgetDefinition(
            slug: 'newsletter',
            title: 'خبرنامه',
            description: 'عضویت ایمیلی در خبرنامه',
            icon: '✉',
            schema: [
                ['key' => 'text', 'type' => 'text', 'label' => 'متن معرفی'],
            ],
            defaults: ['text' => 'برای دریافت تازه‌ها عضو شوید.'],
            render: function (array $data, RenderContext $ctx) use ($csrf): string {
                return '<form class="ij-newsletter" method="post" action="/newsletter/subscribe">'
                    . $csrf->field()
                    . '<p>' . Sanitize::html($data['text'] ?? '') . '</p>'
                    . '<input type="email" name="email" placeholder="ایمیل شما" required dir="ltr">'
                    . '<button type="submit">عضویت</button></form>';
            },
        ));

        $registry->register(new WidgetDefinition(
            slug: 'about',
            title: 'درباره ما',
            description: 'متن کوتاه معرفی',
            icon: 'ℹ',
            schema: [
                ['key' => 'text', 'type' => 'textarea', 'label' => 'متن'],
            ],
            defaults: ['text' => 'این وب‌سایت با IR-Jalali ساخته شده است.'],
            render: fn(array $data, RenderContext $ctx): string =>
                '<p class="ij-about">' . Sanitize::html($data['text'] ?? '') . '</p>',
        ));
    }
}
