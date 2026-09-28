<?php

declare(strict_types=1);

namespace IRJalali\App\Services;

use IRJalali\App\Repositories\MenuRepository;
use IRJalali\App\Repositories\OptionRepository;
use IRJalali\App\Repositories\PostRepository;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Security\Sanitize;

/**
 * First-run wizard: maps a website TYPE to real seeded data —
 * mode, theme record, starter pages, menus and widgets.
 * Core code is never modified; only data rows are created.
 */
final class SetupService
{
    public function __construct(
        private readonly Database $db,
        private readonly OptionRepository $options,
        private readonly PostRepository $posts,
        private readonly MenuRepository $menus,
        private readonly Auth $auth,
    ) {
    }

    /** @return list<array{value: string, label: string, mode: string, description: string}> */
    public static function websiteTypes(): array
    {
        return [
            ['value' => 'corporate', 'label' => 'شرکتی', 'mode' => 'business', 'description' => 'معرفی شرکت، خدمات و تماس'],
            ['value' => 'blog', 'label' => 'وبلاگ', 'mode' => 'blog', 'description' => 'انتشار نوشته و مقاله'],
            ['value' => 'portfolio', 'label' => 'نمونه‌کار', 'mode' => 'portfolio', 'description' => 'نمایش آثار و پروژه‌ها'],
            ['value' => 'store', 'label' => 'فروشگاه', 'mode' => 'ecommerce', 'description' => 'فروش آنلاین محصولات'],
            ['value' => 'booking', 'label' => 'رزرواسیون', 'mode' => 'booking', 'description' => 'رزرو نوبت و خدمات'],
            ['value' => 'agency', 'label' => 'آژانس', 'mode' => 'business', 'description' => 'آژانس خلاقیت و خدمات'],
            ['value' => 'personal', 'label' => 'شخصی', 'mode' => 'general', 'description' => 'وب‌سایت شخصی و رزومه'],
            ['value' => 'news', 'label' => 'خبری', 'mode' => 'magazine', 'description' => 'پایگاه خبری و مجله'],
            ['value' => 'education', 'label' => 'آموزشی', 'mode' => 'education', 'description' => 'دوره‌ها و آموزش'],
            ['value' => 'realestate', 'label' => 'املاک', 'mode' => 'directory', 'description' => 'آگهی املاک'],
            ['value' => 'medical', 'label' => 'پزشکی', 'mode' => 'booking', 'description' => 'پزشک، کلینیک و نوبت‌دهی'],
            ['value' => 'legal', 'label' => 'حقوقی', 'mode' => 'business', 'description' => 'دفتر وکالت و مشاوره'],
            ['value' => 'restaurant', 'label' => 'رستوران', 'mode' => 'business', 'description' => 'منو و رزرو میز'],
            ['value' => 'custom', 'label' => 'سفارشی', 'mode' => 'custom', 'description' => 'شروع با حداقل ساختار'],
        ];
    }

    public static function isValidType(string $type): bool
    {
        foreach (self::websiteTypes() as $item) {
            if ($item['value'] === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Applies the chosen type. Idempotent: safe to run once; guarded by
     * the setup_completed option in the controller.
     *
     * @return array{mode: string, pages: int}
     */
    public function apply(string $type): array
    {
        $mode = 'custom';
        foreach (self::websiteTypes() as $item) {
            if ($item['value'] === $type) {
                $mode = $item['mode'];
                break;
            }
        }

        $authorId = $this->auth->id();
        $now = date('Y-m-d H:i:s');

        $pages = $this->starterPages($type);
        $created = 0;
        foreach ($pages as $page) {
            if ($this->posts->slugExists('page', $page['slug'])) {
                continue;
            }
            $this->posts->create([
                'post_type' => 'page',
                'title' => $page['title'],
                'slug' => $page['slug'],
                'excerpt' => $page['excerpt'],
                'content' => $page['content'],
                'status' => 'published',
                'author_id' => $authorId,
                'menu_order' => $created,
                'locale' => 'fa_IR',
                'published_at' => $now,
            ]);
            $created++;
        }

        // Primary menu from starter pages.
        $menuId = $this->menus->createMenu('primary', 'فهرست اصلی', 'primary');
        $order = 0;
        foreach ($pages as $page) {
            $post = $this->db->table('posts')
                ->where('post_type', 'page')
                ->where('slug', $page['slug'])
                ->first();
            if ($post === null) {
                continue;
            }
            $this->menus->addItem($menuId, [
                'title' => $page['title'],
                'type' => 'page',
                'url' => '/' . $page['slug'],
                'reference_type' => 'page',
                'reference_id' => $post['id'],
                'ordering' => $order++,
            ]);
        }

        // Theme record (default core theme) + recommended starter widgets.
        $themeId = $this->ensureTheme('ir-default', 'قالب پیش‌فرض آیری');
        $this->ensureWidget('search', 'جستجو');
        $this->ensureWidget('recent-posts', 'نوشته‌های تازه');
        $this->ensureWidget('about', 'درباره ما');
        $this->db->insert('widget_instances', [
            'uuid' => $this->uuid(),
            'widget_id' => null,
            'sidebar' => 'sidebar-main',
            'data_json' => json_encode(['widget' => 'about'], JSON_UNESCAPED_UNICODE),
            'ordering' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Default template row for pages.
        if ($this->db->table('templates')->where('slug', 'page-default')->first() === null) {
            $this->db->insert('templates', [
                'slug' => 'page-default',
                'name' => 'قالب پیش‌فرض برگه',
                'type' => 'page',
                'content_json' => json_encode(['layout' => 'single-column'], JSON_UNESCAPED_UNICODE),
                'is_default' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->options->set('website_type', $type);
        $this->options->set('website_mode', $mode);
        $this->options->set('active_theme', 'ir-default');
        $this->options->set('setup_completed', '1');

        unset($themeId);

        return ['mode' => $mode, 'pages' => $created];
    }

    /** @return list<array{title: string, slug: string, excerpt: string, content: string}> */
    private function starterPages(string $type): array
    {
        $home = match ($type) {
            'store' => 'به فروشگاه ما خوش آمدید. به‌زودی محصولات اینجا نمایش داده می‌شوند.',
            'blog' => 'به وبلاگ ما خوش آمدید. تازه‌ترین نوشته‌ها را اینجا دنبال کنید.',
            'booking', 'medical' => 'به سامانه رزرو خوش آمدید. نوبت خود را به‌سادگی ثبت کنید.',
            'restaurant' => 'به رستوران ما خوش آمدید. طعم‌های به‌یادماندنی در انتظار شماست.',
            'education' => 'به آکادمی ما خوش آمدید. یادگیری را از اینجا شروع کنید.',
            'realestate' => 'به سامانه املاک خوش آمدید. خانه رویایی‌تان را پیدا کنید.',
            'news' => 'به پایگاه خبری ما خوش آمدید. آخرین اخبار را اینجا بخوانید.',
            default => 'به وب‌سایت ما خوش آمدید. این برگه را از پیشخوان ویرایش کنید.',
        };

        $pages = [
            ['title' => 'خانه', 'slug' => 'home', 'excerpt' => 'صفحه اصلی سایت', 'content' => $home],
            ['title' => 'درباره ما', 'slug' => 'about', 'excerpt' => 'آشنایی با ما', 'content' => 'ما یک تیم پرانگیزه هستیم که با آیری‌جلالی این وب‌سایت را ساخته‌ایم. این متن را از بخش محتوا ویرایش کنید.'],
            ['title' => 'تماس با ما', 'slug' => 'contact', 'excerpt' => 'راه‌های ارتباطی', 'content' => 'برای ارتباط با ما از فرم تماس استفاده کنید. نشانی، تلفن و ایمیل خود را اینجا بنویسید.'],
        ];

        if (in_array($type, ['corporate', 'agency', 'legal'], true)) {
            $pages[] = ['title' => 'خدمات ما', 'slug' => 'services', 'excerpt' => 'خدماتی که ارائه می‌دهیم', 'content' => 'فهرست خدمات خود را در این برگه معرفی کنید. هر خدمت را با یک عنوان و توضیح کوتاه بنویسید.'];
        }
        if ($type === 'restaurant') {
            $pages[] = ['title' => 'منو', 'slug' => 'menu', 'excerpt' => 'منوی رستوران', 'content' => 'منوی غذاها و نوشیدنی‌ها را در این برگه قرار دهید.'];
        }

        foreach ($pages as &$page) {
            $page['content'] = '<p>' . Sanitize::html($page['content']) . '</p>';
        }

        return $pages;
    }

    private function ensureTheme(string $slug, string $name): int
    {
        $existing = $this->db->table('themes')->where('slug', $slug)->first();
        if ($existing !== null) {
            $this->db->table('themes')->where('id', $existing['id'])->update(['is_active' => 1]);

            return (int) $existing['id'];
        }
        $now = date('Y-m-d H:i:s');
        $this->db->table('themes')->where('is_active', 1)->update(['is_active' => 0]);

        return (int) $this->db->insert('themes', [
            'slug' => $slug,
            'name' => $name,
            'version' => '1.0.0',
            'author' => 'IR-Jalali Core',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function ensureWidget(string $slug, string $name): void
    {
        if ($this->db->table('widgets')->where('slug', $slug)->first() !== null) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $this->db->insert('widgets', [
            'slug' => $slug,
            'name' => $name,
            'source' => 'core',
            'schema' => json_encode([], JSON_UNESCAPED_UNICODE),
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
