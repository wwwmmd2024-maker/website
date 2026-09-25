<?php

declare(strict_types=1);

namespace IRJalali\Core\Builder;

/**
 * Single source of editor metadata for every builder node type.
 * Field control types understood by the JS app:
 * text, textarea, richtext, number, range, select, checkbox, color,
 * image, images, url, icon, binding, query, repeater, block, widget,
 * form-select, menu-select, date-range, roles.
 */
final class ComponentCatalog
{
    public const CATEGORIES = [
        'layout' => 'چیدمان',
        'basic' => 'پایه',
        'typography' => 'تایپوگرافی',
        'content' => 'محتوا',
        'media' => 'رسانه',
        'marketing' => 'بازاریابی',
        'dynamic' => 'داینامیک',
        'forms' => 'فرم‌ها',
        'theme' => 'قالب',
        'advanced' => 'پیشرفته',
    ];

    public const ANIMATIONS = [
        'fade-up' => 'ظهور از پایین',
        'fade-down' => 'ظهور از بالا',
        'zoom' => 'بزرگ‌نمایی',
        'slide-right' => 'لغزش از راست',
        'slide-left' => 'لغزش از چپ',
        'flip' => 'چرخش',
    ];

    public const CONDITION_RULES = [
        'logged_in' => ['label' => 'کاربر وارد شده باشد', 'value' => null],
        'logged_out' => ['label' => 'کاربر مهمان باشد', 'value' => null],
        'user_role' => ['label' => 'نقش کاربر', 'value' => 'roles'],
        'device' => ['label' => 'دستگاه', 'value' => 'device'],
        'post_type' => ['label' => 'نوع نوشته', 'value' => 'post_type'],
        'date_range' => ['label' => 'بازه تاریخ', 'value' => 'date-range'],
    ];

    public const DYNAMIC_BINDINGS = [
        'site.title' => 'عنوان سایت',
        'site.tagline' => 'معرفی سایت',
        'site.url' => 'آدرس سایت',
        'site.year' => 'سال جاری',
        'post.title' => 'عنوان نوشته',
        'post.slug' => 'نامک نوشته',
        'post.excerpt' => 'خلاصه نوشته',
        'post.content' => 'متن نوشته',
        'post.featured_image' => 'تصویر شاخص',
        'post.url' => 'آدرس نوشته',
        'post.published_at' => 'تاریخ انتشار',
        'post.author' => 'نام نویسنده',
        'author.name' => 'نام نویسنده',
        'author.email' => 'ایمیل نویسنده',
        'user.name' => 'نام کاربر جاری',
        'user.email' => 'ایمیل کاربر جاری',
        'date.now' => 'زمان حال',
        'date.today' => 'امروز',
        'date.year' => 'سال',
    ];

    public const ICONS = [
        'check' => '✓ تیک',
        'star' => '★ ستاره',
        'phone' => '☎ تلفن',
        'mail' => '✉ ایمیل',
        'pin' => '📍 سنجاق',
        'clock' => '◷ ساعت',
        'arrow' => '← فلش',
        'plus' => '＋ بعلاوه',
        'search' => '🔍 جستجو',
        'user' => '👤 کاربر',
        'cart' => '🛒 سبد',
        'heart' => '♥ قلب',
    ];

    /**
     * Generic style-control groups (STYLE_PROPS subset per group).
     *
     * @return array<string, array{label: string, controls: list<array<string, mixed>>}>
     */
    public static function styleGroups(): array
    {
        return [
            'text' => ['label' => 'متن', 'controls' => [
                ['key' => 'color', 'type' => 'color', 'label' => 'رنگ متن'],
                ['key' => 'fontSize', 'type' => 'text', 'label' => 'اندازه قلم', 'placeholder' => '16px'],
                ['key' => 'fontWeight', 'type' => 'select', 'label' => 'وزن قلم', 'options' => ['400' => 'معمولی', '500' => 'متوسط', '700' => 'توپر', '900' => 'خیلی توپر']],
                ['key' => 'textAlign', 'type' => 'select', 'label' => 'تراز', 'options' => ['right' => 'راست', 'center' => 'وسط', 'left' => 'چپ', 'justify' => 'هم‌تراز']],
                ['key' => 'lineHeight', 'type' => 'text', 'label' => 'ارتفاع خط', 'placeholder' => '1.8'],
                ['key' => 'letterSpacing', 'type' => 'text', 'label' => 'فاصله حروف', 'placeholder' => '0px'],
            ]],
            'background' => ['label' => 'پس‌زمینه', 'controls' => [
                ['key' => 'background', 'type' => 'color', 'label' => 'رنگ پس‌زمینه'],
                ['key' => 'backgroundGradient', 'type' => 'text', 'label' => 'گرادیان (CSS)', 'placeholder' => 'linear-gradient(...)', 'dir' => 'ltr'],
            ]],
            'spacing' => ['label' => 'فاصله‌ها', 'controls' => [
                ['key' => 'marginTop', 'type' => 'text', 'label' => 'حاشیه بالا', 'placeholder' => '0px'],
                ['key' => 'marginBottom', 'type' => 'text', 'label' => 'حاشیه پایین', 'placeholder' => '0px'],
                ['key' => 'marginRight', 'type' => 'text', 'label' => 'حاشیه راست', 'placeholder' => '0px'],
                ['key' => 'marginLeft', 'type' => 'text', 'label' => 'حاشیه چپ', 'placeholder' => '0px'],
                ['key' => 'paddingTop', 'type' => 'text', 'label' => 'پدینگ بالا', 'placeholder' => '0px'],
                ['key' => 'paddingBottom', 'type' => 'text', 'label' => 'پدینگ پایین', 'placeholder' => '0px'],
                ['key' => 'paddingRight', 'type' => 'text', 'label' => 'پدینگ راست', 'placeholder' => '0px'],
                ['key' => 'paddingLeft', 'type' => 'text', 'label' => 'پدینگ چپ', 'placeholder' => '0px'],
            ]],
            'border' => ['label' => 'کادر', 'controls' => [
                ['key' => 'borderWidth', 'type' => 'text', 'label' => 'ضخامت', 'placeholder' => '1px'],
                ['key' => 'borderStyle', 'type' => 'select', 'label' => 'سبک', 'options' => ['solid' => 'توپر', 'dashed' => 'خط‌چین', 'dotted' => 'نقطه‌چین', 'none' => 'بدون کادر']],
                ['key' => 'borderColor', 'type' => 'color', 'label' => 'رنگ کادر'],
                ['key' => 'borderRadius', 'type' => 'text', 'label' => 'گردی گوشه', 'placeholder' => '8px'],
                ['key' => 'boxShadow', 'type' => 'text', 'label' => 'سایه (CSS)', 'placeholder' => '0 8px 24px rgba(0,0,0,.12)', 'dir' => 'ltr'],
            ]],
            'size' => ['label' => 'اندازه', 'controls' => [
                ['key' => 'width', 'type' => 'text', 'label' => 'عرض', 'placeholder' => 'auto'],
                ['key' => 'maxWidth', 'type' => 'text', 'label' => 'حداکثر عرض', 'placeholder' => '1200px'],
                ['key' => 'height', 'type' => 'text', 'label' => 'ارتفاع', 'placeholder' => 'auto'],
                ['key' => 'minHeight', 'type' => 'text', 'label' => 'حداقل ارتفاع', 'placeholder' => '0px'],
            ]],
            'layout' => ['label' => 'چیدمان داخلی', 'controls' => [
                ['key' => 'display', 'type' => 'select', 'label' => 'نمایش', 'options' => ['block' => 'بلوک', 'flex' => 'فلکس', 'grid' => 'گرید', 'inline-block' => 'درون‌خطی']],
                ['key' => 'flexDirection', 'type' => 'select', 'label' => 'جهت فلکس', 'options' => ['row' => 'افقی', 'column' => 'عمودی']],
                ['key' => 'justifyContent', 'type' => 'select', 'label' => 'تراز افقی', 'options' => ['flex-start' => 'شروع', 'center' => 'وسط', 'flex-end' => 'پایان', 'space-between' => 'فاصله‌دار']],
                ['key' => 'alignItems', 'type' => 'select', 'label' => 'تراز عمودی', 'options' => ['stretch' => 'کشیده', 'flex-start' => 'شروع', 'center' => 'وسط', 'flex-end' => 'پایان']],
                ['key' => 'gap', 'type' => 'text', 'label' => 'فاصله اقلام', 'placeholder' => '16px'],
                ['key' => 'gridColumns', 'type' => 'text', 'label' => 'ستون‌های گرید', 'placeholder' => 'repeat(3, 1fr)', 'dir' => 'ltr'],
            ]],
            'position' => ['label' => 'موقعیت', 'controls' => [
                ['key' => 'position', 'type' => 'select', 'label' => 'موقعیت', 'options' => ['static' => 'عادی', 'relative' => 'نسبی', 'absolute' => 'مطلق', 'sticky' => 'چسبان']],
                ['key' => 'top', 'type' => 'text', 'label' => 'بالا', 'placeholder' => 'auto'],
                ['key' => 'right', 'type' => 'text', 'label' => 'راست', 'placeholder' => 'auto'],
                ['key' => 'bottom', 'type' => 'text', 'label' => 'پایین', 'placeholder' => 'auto'],
                ['key' => 'left', 'type' => 'text', 'label' => 'چپ', 'placeholder' => 'auto'],
                ['key' => 'zIndex', 'type' => 'number', 'label' => 'ترتیب لایه', 'placeholder' => 'auto'],
            ]],
            'effects' => ['label' => 'جلوه‌ها', 'controls' => [
                ['key' => 'opacity', 'type' => 'range', 'label' => 'شفافیت', 'min' => 0, 'max' => 1, 'step' => 0.05],
                ['key' => 'overflow', 'type' => 'select', 'label' => 'سرریز', 'options' => ['visible' => 'مرئی', 'hidden' => 'مخفی', 'auto' => 'خودکار']],
            ]],
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'section' => [
                'title' => 'سکشن', 'icon' => '▤', 'category' => 'layout', 'container' => true,
                'description' => 'بخش تمام‌عرض صفحه با محتوای داخلی.',
                'fields' => [],
                'defaults' => ['content' => []],
            ],
            'container' => [
                'title' => 'کانتینر', 'icon' => '▢', 'category' => 'layout', 'container' => true,
                'description' => 'ظرف محدودکننده عرض با چیدمان فلکس/گرید.',
                'fields' => [],
                'defaults' => ['content' => [], 'style' => ['maxWidth' => '1200px']],
            ],
            'grid' => [
                'title' => 'گرید', 'icon' => '▦', 'category' => 'layout', 'container' => true,
                'description' => 'شبکه چندستونه؛ فرزندان در ستون‌ها قرار می‌گیرند.',
                'fields' => [
                    ['key' => 'columns', 'type' => 'number', 'label' => 'تعداد ستون', 'min' => 1, 'max' => 6, 'default' => 3],
                ],
                'defaults' => ['content' => ['columns' => 3], 'style' => ['display' => 'grid', 'gridColumns' => 'repeat(3, 1fr)', 'gap' => '20px']],
            ],
            'columns' => [
                'title' => 'ستون‌ها', 'icon' => '▥', 'category' => 'layout', 'container' => true,
                'description' => 'ردیف ستونی؛ هنگام افزودن، ستون‌ها ساخته می‌شوند.',
                'fields' => [
                    ['key' => 'count', 'type' => 'select', 'label' => 'تعداد ستون', 'options' => ['1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '6' => '۶'], 'default' => '2'],
                ],
                'defaults' => ['content' => ['count' => 2]],
            ],
            'column' => [
                'title' => 'ستون', 'icon' => '▌', 'category' => 'layout', 'container' => true,
                'description' => 'یک ستون داخل ردیف ستون‌ها.',
                'fields' => [],
                'defaults' => ['content' => []],
                'palette' => false,
            ],
            'heading' => [
                'title' => 'تیتر', 'icon' => 'H', 'category' => 'typography', 'container' => false,
                'description' => 'تیتر H1 تا H6.',
                'fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'متن تیتر', 'dynamic' => true, 'default' => 'تیتر نمونه'],
                    ['key' => 'level', 'type' => 'select', 'label' => 'سطح', 'options' => ['1' => 'H1', '2' => 'H2', '3' => 'H3', '4' => 'H4', '5' => 'H5', '6' => 'H6'], 'default' => 2],
                ],
                'defaults' => ['content' => ['text' => 'تیتر نمونه', 'level' => 2]],
            ],
            'text' => [
                'title' => 'متن', 'icon' => '¶', 'category' => 'basic', 'container' => false,
                'description' => 'پاراگراف متنی ساده.',
                'fields' => [
                    ['key' => 'text', 'type' => 'textarea', 'label' => 'متن', 'dynamic' => true, 'default' => 'متن نمونه…'],
                ],
                'defaults' => ['content' => ['text' => 'متن نمونه…']],
            ],
            'richtext' => [
                'title' => 'متن غنی', 'icon' => '🖋', 'category' => 'content', 'container' => false,
                'description' => 'متن با قالب‌بندی HTML (پاک‌سازی‌شده).',
                'fields' => [
                    ['key' => 'html', 'type' => 'richtext', 'label' => 'محتوا', 'dynamic' => true, 'default' => '<p>متن <strong>نمونه</strong>…</p>'],
                ],
                'defaults' => ['content' => ['html' => '<p>متن <strong>نمونه</strong>…</p>']],
            ],
            'button' => [
                'title' => 'دکمه', 'icon' => '▣', 'category' => 'basic', 'container' => false,
                'description' => 'دکمه لینک‌دار.',
                'fields' => [
                    ['key' => 'text', 'type' => 'text', 'label' => 'متن دکمه', 'dynamic' => true, 'default' => 'دکمه'],
                    ['key' => 'url', 'type' => 'url', 'label' => 'آدرس', 'default' => '#'],
                    ['key' => 'target', 'type' => 'select', 'label' => 'هدف', 'options' => ['_self' => 'همان تب', '_blank' => 'تب جدید'], 'default' => '_self'],
                    ['key' => 'variant', 'type' => 'select', 'label' => 'ظاهر', 'options' => ['primary' => 'اصلی', 'secondary' => 'ثانویه', 'outline' => 'کادری', 'ghost' => 'نامرئی'], 'default' => 'primary'],
                    ['key' => 'size', 'type' => 'select', 'label' => 'اندازه', 'options' => ['sm' => 'کوچک', 'md' => 'متوسط', 'lg' => 'بزرگ'], 'default' => 'md'],
                ],
                'defaults' => ['content' => ['text' => 'دکمه', 'url' => '#', 'variant' => 'primary', 'size' => 'md']],
            ],
            'icon' => [
                'title' => 'آیکون', 'icon' => '★', 'category' => 'media', 'container' => false,
                'description' => 'آیکون SVG داخلی.',
                'fields' => [
                    ['key' => 'name', 'type' => 'icon', 'label' => 'آیکون', 'default' => 'star'],
                    ['key' => 'size', 'type' => 'number', 'label' => 'اندازه (px)', 'min' => 12, 'max' => 160, 'default' => 32],
                ],
                'defaults' => ['content' => ['name' => 'star', 'size' => 32]],
            ],
            'divider' => [
                'title' => 'جداکننده', 'icon' => '―', 'category' => 'basic', 'container' => false,
                'description' => 'خط افقی جداکننده.',
                'fields' => [],
                'defaults' => ['content' => []],
            ],
            'spacer' => [
                'title' => 'فاصله‌گذار', 'icon' => '⇕', 'category' => 'layout', 'container' => false,
                'description' => 'فضای خالی عمودی.',
                'fields' => [
                    ['key' => 'height', 'type' => 'number', 'label' => 'ارتفاع (px)', 'min' => 0, 'max' => 600, 'default' => 40],
                ],
                'defaults' => ['content' => ['height' => 40]],
            ],
            'image' => [
                'title' => 'تصویر', 'icon' => '🖼', 'category' => 'media', 'container' => false,
                'description' => 'تصویر با زیرنویس و لینک اختیاری.',
                'fields' => [
                    ['key' => 'src', 'type' => 'image', 'label' => 'تصویر', 'dynamic' => true],
                    ['key' => 'alt', 'type' => 'text', 'label' => 'متن جایگزین', 'default' => ''],
                    ['key' => 'width', 'type' => 'number', 'label' => 'عرض (px، خالی=خودکار)', 'min' => 0, 'max' => 3000],
                    ['key' => 'link', 'type' => 'url', 'label' => 'لینک', 'default' => ''],
                    ['key' => 'caption', 'type' => 'text', 'label' => 'زیرنویس', 'default' => ''],
                ],
                'defaults' => ['content' => ['src' => '', 'alt' => '']],
            ],
            'video' => [
                'title' => 'ویدیو', 'icon' => '▶', 'category' => 'media', 'container' => false,
                'description' => 'ویدیو (یوتیوب، ویمو، آپارات یا فایل مستقیم).',
                'fields' => [
                    ['key' => 'src', 'type' => 'url', 'label' => 'آدرس ویدیو', 'help' => 'لینک یوتیوب/ویمو/آپارات یا فایل mp4', 'dir' => 'ltr'],
                    ['key' => 'poster', 'type' => 'image', 'label' => 'پوستر (فایل مستقیم)'],
                    ['key' => 'autoplay', 'type' => 'checkbox', 'label' => 'پخش خودکار (بی‌صدا)'],
                    ['key' => 'controls', 'type' => 'checkbox', 'label' => 'نمایش کنترل‌ها', 'default' => true],
                ],
                'defaults' => ['content' => ['src' => '', 'controls' => true]],
            ],
            'gallery' => [
                'title' => 'گالری', 'icon' => '▦', 'category' => 'media', 'container' => false,
                'description' => 'شبکه تصاویر (تا ۳۰ تصویر).',
                'fields' => [
                    ['key' => 'images', 'type' => 'images', 'label' => 'تصاویر'],
                    ['key' => 'columns', 'type' => 'number', 'label' => 'ستون‌ها', 'min' => 1, 'max' => 6, 'default' => 3],
                ],
                'defaults' => ['content' => ['images' => [], 'columns' => 3]],
            ],
            'map' => [
                'title' => 'نقشه', 'icon' => '🗺', 'category' => 'advanced', 'container' => false,
                'description' => 'نقشه OpenStreetMap بدون نیاز به کلید.',
                'fields' => [
                    ['key' => 'lat', 'type' => 'text', 'label' => 'عرض جغرافیایی', 'placeholder' => '35.6892', 'dir' => 'ltr'],
                    ['key' => 'lng', 'type' => 'text', 'label' => 'طول جغرافیایی', 'placeholder' => '51.3890', 'dir' => 'ltr'],
                    ['key' => 'zoom', 'type' => 'number', 'label' => 'زوم', 'min' => 1, 'max' => 19, 'default' => 14],
                    ['key' => 'address', 'type' => 'text', 'label' => 'آدرس (جایگزین مختصات)', 'help' => 'اگر مختصات خالی باشد، لینک جستجوی آدرس نمایش داده می‌شود.'],
                ],
                'defaults' => ['content' => ['lat' => '35.6892', 'lng' => '51.3890', 'zoom' => 14]],
            ],
            'card' => [
                'title' => 'کارت', 'icon' => '🃏', 'category' => 'content', 'container' => false,
                'description' => 'کارت تصویر + تیتر + متن + لینک.',
                'fields' => [
                    ['key' => 'title', 'type' => 'text', 'label' => 'تیتر', 'dynamic' => true, 'default' => 'عنوان کارت'],
                    ['key' => 'text', 'type' => 'textarea', 'label' => 'متن', 'dynamic' => true, 'default' => 'توضیح کوتاه…'],
                    ['key' => 'image', 'type' => 'image', 'label' => 'تصویر'],
                    ['key' => 'link', 'type' => 'url', 'label' => 'لینک', 'default' => ''],
                    ['key' => 'link_text', 'type' => 'text', 'label' => 'متن لینک', 'default' => 'بیشتر بخوانید'],
                ],
                'defaults' => ['content' => ['title' => 'عنوان کارت', 'text' => 'توضیح کوتاه…']],
            ],
            'tabs' => [
                'title' => 'تب‌ها', 'icon' => '🗂', 'category' => 'content', 'container' => false,
                'description' => 'تب‌های بدون جاوااسکریپت (تا ۱۲ تب).',
                'fields' => [
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'تب‌ها', 'max' => 12, 'fields' => [
                        ['key' => 'title', 'type' => 'text', 'label' => 'عنوان تب', 'default' => 'تب'],
                        ['key' => 'content', 'type' => 'richtext', 'label' => 'محتوا', 'default' => '<p>محتوای تب…</p>'],
                    ]],
                ],
                'defaults' => ['content' => ['items' => [['title' => 'تب اول', 'content' => '<p>محتوای تب اول…</p>'], ['title' => 'تب دوم', 'content' => '<p>محتوای تب دوم…</p>']]]],
            ],
            'accordion' => [
                'title' => 'آکاردئون', 'icon' => '☰', 'category' => 'content', 'container' => false,
                'description' => 'موارد بازشونده (تا ۳۰ مورد).',
                'fields' => [
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'موارد', 'max' => 30, 'fields' => [
                        ['key' => 'title', 'type' => 'text', 'label' => 'عنوان', 'default' => 'مورد'],
                        ['key' => 'content', 'type' => 'richtext', 'label' => 'محتوا', 'default' => '<p>جزئیات…</p>'],
                    ]],
                ],
                'defaults' => ['content' => ['items' => [['title' => 'مورد اول', 'content' => '<p>جزئیات…</p>']]]],
            ],
            'faq' => [
                'title' => 'سوالات متداول', 'icon' => '❓', 'category' => 'content', 'container' => false,
                'description' => 'آکاردئون پرسش و پاسخ.',
                'fields' => [
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'پرسش‌ها', 'max' => 30, 'fields' => [
                        ['key' => 'question', 'type' => 'text', 'label' => 'پرسش', 'default' => 'سوال؟'],
                        ['key' => 'answer', 'type' => 'richtext', 'label' => 'پاسخ', 'default' => '<p>پاسخ…</p>'],
                    ]],
                ],
                'defaults' => ['content' => ['items' => [['question' => 'سوال نمونه؟', 'answer' => '<p>پاسخ نمونه…</p>']]]],
            ],
            'carousel' => [
                'title' => 'چرخ‌فلک', 'icon' => '🎠', 'category' => 'content', 'container' => false,
                'description' => 'اسلایدر تصاویر با پخش خودکار (تا ۲۰ اسلاید).',
                'fields' => [
                    ['key' => 'slides', 'type' => 'repeater', 'label' => 'اسلایدها', 'max' => 20, 'fields' => [
                        ['key' => 'image', 'type' => 'image', 'label' => 'تصویر'],
                        ['key' => 'title', 'type' => 'text', 'label' => 'عنوان'],
                        ['key' => 'text', 'type' => 'text', 'label' => 'توضیح'],
                    ]],
                    ['key' => 'autoplay', 'type' => 'checkbox', 'label' => 'پخش خودکار'],
                    ['key' => 'interval', 'type' => 'number', 'label' => 'فاصله (میلی‌ثانیه)', 'min' => 1000, 'max' => 15000, 'default' => 4000],
                ],
                'defaults' => ['content' => ['slides' => [], 'autoplay' => false, 'interval' => 4000]],
            ],
            'slider' => [
                'title' => 'اسلایدر', 'icon' => '🖼', 'category' => 'content', 'container' => false,
                'description' => 'اسلایدر تمام‌عرض (تا ۲۰ اسلاید).',
                'fields' => [
                    ['key' => 'slides', 'type' => 'repeater', 'label' => 'اسلایدها', 'max' => 20, 'fields' => [
                        ['key' => 'image', 'type' => 'image', 'label' => 'تصویر'],
                        ['key' => 'title', 'type' => 'text', 'label' => 'عنوان'],
                        ['key' => 'text', 'type' => 'text', 'label' => 'توضیح'],
                    ]],
                    ['key' => 'autoplay', 'type' => 'checkbox', 'label' => 'پخش خودکار', 'default' => true],
                    ['key' => 'interval', 'type' => 'number', 'label' => 'فاصله (میلی‌ثانیه)', 'min' => 1000, 'max' => 15000, 'default' => 5000],
                ],
                'defaults' => ['content' => ['slides' => [], 'autoplay' => true, 'interval' => 5000]],
            ],
            'counter' => [
                'title' => 'شمارنده', 'icon' => '🔢', 'category' => 'marketing', 'container' => false,
                'description' => 'عدد شمارنده متحرک.',
                'fields' => [
                    ['key' => 'number', 'type' => 'number', 'label' => 'عدد', 'default' => 100],
                    ['key' => 'suffix', 'type' => 'text', 'label' => 'پسوند', 'placeholder' => '٪ / +'],
                    ['key' => 'label', 'type' => 'text', 'label' => 'برچسب', 'default' => 'مشتری'],
                ],
                'defaults' => ['content' => ['number' => 100, 'suffix' => '+', 'label' => 'مشتری']],
            ],
            'progress' => [
                'title' => 'نوار پیشرفت', 'icon' => '📊', 'category' => 'marketing', 'container' => false,
                'description' => 'نوار درصد پیشرفت.',
                'fields' => [
                    ['key' => 'percent', 'type' => 'range', 'label' => 'درصد', 'min' => 0, 'max' => 100, 'default' => 70],
                    ['key' => 'label', 'type' => 'text', 'label' => 'برچسب', 'default' => 'مهارت'],
                ],
                'defaults' => ['content' => ['percent' => 70, 'label' => 'مهارت']],
            ],
            'pricing' => [
                'title' => 'قیمت‌گذاری', 'icon' => '💳', 'category' => 'marketing', 'container' => false,
                'description' => 'جدول پلن‌های قیمت (تا ۶ پلن).',
                'fields' => [
                    ['key' => 'plans', 'type' => 'repeater', 'label' => 'پلن‌ها', 'max' => 6, 'fields' => [
                        ['key' => 'name', 'type' => 'text', 'label' => 'نام پلن', 'default' => 'پلن'],
                        ['key' => 'price', 'type' => 'text', 'label' => 'قیمت', 'default' => '۹۹٬۰۰۰ تومان'],
                        ['key' => 'features', 'type' => 'textarea', 'label' => 'ویژگی‌ها (هر خط یکی)', 'default' => "ویژگی اول\nویژگی دوم"],
                        ['key' => 'featured', 'type' => 'checkbox', 'label' => 'پلن ویژه'],
                        ['key' => 'url', 'type' => 'url', 'label' => 'لینک خرید'],
                        ['key' => 'cta', 'type' => 'text', 'label' => 'متن دکمه', 'default' => 'انتخاب'],
                    ]],
                ],
                'defaults' => ['content' => ['plans' => [['name' => 'پایه', 'price' => '۹۹٬۰۰۰ تومان', 'features' => "ویژگی اول\nویژگی دوم", 'featured' => false]]]],
            ],
            'testimonial' => [
                'title' => 'نظر مشتری', 'icon' => '💬', 'category' => 'marketing', 'container' => false,
                'description' => 'نقل‌قول مشتریان (تا ۱۲ مورد).',
                'fields' => [
                    ['key' => 'items', 'type' => 'repeater', 'label' => 'نظرات', 'max' => 12, 'fields' => [
                        ['key' => 'text', 'type' => 'textarea', 'label' => 'متن نظر', 'default' => 'نظر مشتری…'],
                        ['key' => 'name', 'type' => 'text', 'label' => 'نام', 'default' => 'نام مشتری'],
                    ]],
                ],
                'defaults' => ['content' => ['items' => [['text' => 'نظر مشتری…', 'name' => 'نام مشتری']]]],
            ],
            'team' => [
                'title' => 'تیم', 'icon' => '👥', 'category' => 'content', 'container' => false,
                'description' => 'اعضای تیم با تصویر (تا ۱۲ نفر).',
                'fields' => [
                    ['key' => 'members', 'type' => 'repeater', 'label' => 'اعضا', 'max' => 12, 'fields' => [
                        ['key' => 'name', 'type' => 'text', 'label' => 'نام', 'default' => 'نام عضو'],
                        ['key' => 'role', 'type' => 'text', 'label' => 'سمت', 'default' => 'سمت'],
                        ['key' => 'image', 'type' => 'image', 'label' => 'تصویر'],
                    ]],
                ],
                'defaults' => ['content' => ['members' => [['name' => 'نام عضو', 'role' => 'سمت']]]],
            ],
            'logogrid' => [
                'title' => 'شبکه لوگو', 'icon' => '🏢', 'category' => 'marketing', 'container' => false,
                'description' => 'ردیف لوگوی مشتریان/همکاران (تا ۲۴ لوگو).',
                'fields' => [
                    ['key' => 'images', 'type' => 'images', 'label' => 'لوگوها'],
                ],
                'defaults' => ['content' => ['images' => []]],
            ],
            'menu' => [
                'title' => 'منو', 'icon' => '☰', 'category' => 'theme', 'container' => false,
                'description' => 'نمایش یک منوی ناوبری.',
                'fields' => [
                    ['key' => 'menu_id', 'type' => 'menu-select', 'label' => 'منو'],
                    ['key' => 'location', 'type' => 'text', 'label' => 'موقعیت (جایگزین)', 'help' => 'اگر منو انتخاب نشود، از موقعیت استفاده می‌شود.', 'placeholder' => 'primary'],
                ],
                'defaults' => ['content' => ['menu_id' => 0, 'location' => 'primary']],
            ],
            'search' => [
                'title' => 'جستجو', 'icon' => '🔍', 'category' => 'content', 'container' => false,
                'description' => 'فرم جستجوی سایت.',
                'fields' => [
                    ['key' => 'placeholder', 'type' => 'text', 'label' => 'متن راهنما', 'default' => 'جستجو…'],
                ],
                'defaults' => ['content' => ['placeholder' => 'جستجو…']],
            ],
            'form' => [
                'title' => 'فرم', 'icon' => '📝', 'category' => 'forms', 'container' => false,
                'description' => 'فرم تماس، خبرنامه یا فرم سفارشی.',
                'fields' => [
                    ['key' => 'preset', 'type' => 'select', 'label' => 'قالب آماده', 'options' => ['contact' => 'تماس با ما', 'newsletter' => 'خبرنامه'], 'default' => 'contact'],
                    ['key' => 'form_id', 'type' => 'form-select', 'label' => 'یا فرم ساخته‌شده', 'help' => 'اگر فرم انتخاب شود، جای قالب آماده را می‌گیرد.'],
                ],
                'defaults' => ['content' => ['preset' => 'contact', 'form_id' => 0]],
            ],
            'postlist' => [
                'title' => 'فهرست نوشته‌ها', 'icon' => '📰', 'category' => 'dynamic', 'container' => false,
                'description' => 'فهرست خودکار نوشته‌ها از کوئری.',
                'fields' => [
                    ['key' => 'query', 'type' => 'query', 'label' => 'کوئری'],
                    ['key' => 'layout', 'type' => 'select', 'label' => 'چیدمان', 'options' => ['list' => 'فهرستی', 'compact' => 'فشرده'], 'default' => 'list'],
                ],
                'defaults' => ['content' => ['query' => ['post_type' => 'post', 'limit' => 5], 'layout' => 'list']],
            ],
            'postgrid' => [
                'title' => 'گرید نوشته‌ها', 'icon' => '▦', 'category' => 'dynamic', 'container' => false,
                'description' => 'شبکه کارتی نوشته‌ها از کوئری.',
                'fields' => [
                    ['key' => 'query', 'type' => 'query', 'label' => 'کوئری'],
                    ['key' => 'layout', 'type' => 'select', 'label' => 'چیدمان', 'options' => ['grid' => 'گرید', 'masonry' => 'آجری'], 'default' => 'grid'],
                ],
                'defaults' => ['content' => ['query' => ['post_type' => 'post', 'limit' => 6], 'layout' => 'grid']],
            ],
            'queryloop' => [
                'title' => 'حلقه کوئری', 'icon' => '🔁', 'category' => 'dynamic', 'container' => true,
                'description' => 'تکرار قالب داخلی برای هر نتیجه کوئری.',
                'fields' => [
                    ['key' => 'query', 'type' => 'query', 'label' => 'کوئری'],
                ],
                'defaults' => ['content' => ['query' => ['post_type' => 'post', 'limit' => 6]]],
            ],
            'dynamicfield' => [
                'title' => 'فیلد داینامیک', 'icon' => '⚡', 'category' => 'dynamic', 'container' => false,
                'description' => 'نمایش مقدار داینامیک (نوشته، کاربر، سایت…).',
                'fields' => [
                    ['key' => 'binding', 'type' => 'binding', 'label' => 'بایندینگ', 'default' => '{{post.title}}'],
                    ['key' => 'fallback', 'type' => 'text', 'label' => 'متن جایگزین', 'dynamic' => true, 'default' => ''],
                ],
                'defaults' => ['content' => ['binding' => '{{post.title}}', 'fallback' => '']],
            ],
            'dynamicimage' => [
                'title' => 'تصویر داینامیک', 'icon' => '🖼', 'category' => 'dynamic', 'container' => false,
                'description' => 'تصویر از بایندینگ داینامیک.',
                'fields' => [
                    ['key' => 'binding', 'type' => 'binding', 'label' => 'بایندینگ', 'default' => '{{post.featured_image}}'],
                    ['key' => 'alt', 'type' => 'text', 'label' => 'متن جایگزین', 'dynamic' => true, 'default' => ''],
                ],
                'defaults' => ['content' => ['binding' => '{{post.featured_image}}', 'alt' => '']],
            ],
            'dynamiclink' => [
                'title' => 'لینک داینامیک', 'icon' => '🔗', 'category' => 'dynamic', 'container' => false,
                'description' => 'لینک با آدرس داینامیک.',
                'fields' => [
                    ['key' => 'binding', 'type' => 'binding', 'label' => 'بایندینگ آدرس', 'default' => '{{post.url}}'],
                    ['key' => 'text', 'type' => 'text', 'label' => 'متن لینک', 'dynamic' => true, 'default' => 'بیشتر بخوانید'],
                ],
                'defaults' => ['content' => ['binding' => '{{post.url}}', 'text' => 'بیشتر بخوانید']],
            ],
            'block' => [
                'title' => 'بلاک', 'icon' => '🧩', 'category' => 'advanced', 'container' => false,
                'description' => 'درج بلاک ثبت‌شده (هسته/پلاگین/قالب).',
                'fields' => [
                    ['key' => 'block', 'type' => 'block', 'label' => 'بلاک'],
                ],
                'defaults' => ['content' => ['block' => '', 'data' => []]],
            ],
            'widget' => [
                'title' => 'ویجت', 'icon' => '◧', 'category' => 'advanced', 'container' => false,
                'description' => 'درج ویجت ثبت‌شده.',
                'fields' => [
                    ['key' => 'widget', 'type' => 'widget', 'label' => 'ویجت'],
                ],
                'defaults' => ['content' => ['widget' => '', 'data' => []]],
            ],
            'html' => [
                'title' => 'HTML سفارشی', 'icon' => '⌨', 'category' => 'advanced', 'container' => false,
                'description' => 'کد HTML خام (پاک‌سازی‌شده). فقط کاربران مطمئن.',
                'fields' => [
                    ['key' => 'code', 'type' => 'textarea', 'label' => 'کد HTML', 'dir' => 'ltr', 'rows' => 8, 'default' => '<!-- کد شما -->'],
                ],
                'defaults' => ['content' => ['code' => '<!-- کد شما -->']],
                'permission' => 'pages.publish',
            ],
        ];
    }

    public static function get(string $type): ?array
    {
        return self::all()[$type] ?? null;
    }

    public static function isContainer(string $type): bool
    {
        return (bool) (self::all()[$type]['container'] ?? false);
    }
}
