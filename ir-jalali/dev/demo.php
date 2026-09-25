<?php

declare(strict_types=1);

/**
 * DEV-ONLY: seed demo content (pages, posts, menu, and sample data for the
 * official modules: products, services, courses, plans, listings).
 * Usage: php dev/demo.php
 */

$base = dirname(__DIR__);
require $base . '/core/Kernel/Autoloader.php';
\IRJalali\Core\Kernel\Autoloader::register($base);

$app = \IRJalali\Core\Kernel\Application::boot($base);
$db = $app->make(\IRJalali\Core\Database\Database::class);
$posts = $app->make(\IRJalali\App\Repositories\PostRepository::class);
$menus = $app->make(\IRJalali\App\Repositories\MenuRepository::class);
$now = date('Y-m-d H:i:s');

// ── Pages & posts ────────────────────────────────────────────────
$pages = [
    ['title' => 'خانه', 'slug' => 'home', 'content' => '<p>به سایت من خوش آمدید. این یک نسخه نمایشی از سامانه IR-Jalali است.</p>'],
    ['title' => 'درباره ما', 'slug' => 'about', 'content' => '<p>IR-Jalali یک سیستم‌عامل وب جامع فارسی با تقویم جلالی است.</p>'],
    ['title' => 'تماس', 'slug' => 'contact', 'content' => '<p>با ما در تماس باشید.</p>'],
];
foreach ($pages as $page) {
    if (!$posts->slugExists('page', $page['slug'])) {
        $posts->create($page + ['post_type' => 'page', 'status' => 'published', 'author_id' => 1, 'published_at' => $now]);
        echo "page: {$page['slug']}\n";
    }
}
$blog = [
    ['title' => 'نخستین نوشته', 'slug' => 'first-post', 'content' => '<p>سلام دنیا!</p>', 'excerpt' => 'اولین نوشته این سایت.'],
    ['title' => 'دومین نوشته', 'slug' => 'second-post', 'content' => '<p>نوشته دوم درباره امکانات سامانه.</p>', 'excerpt' => 'آشنایی با امکانات.'],
];
foreach ($blog as $post) {
    if (!$posts->slugExists('post', $post['slug'])) {
        $posts->create($post + ['post_type' => 'post', 'status' => 'published', 'author_id' => 1, 'published_at' => $now]);
        echo "post: {$post['slug']}\n";
    }
}

// ── Menu ─────────────────────────────────────────────────────────
$menuId = null;
$existing = $db->table('menus')->where('slug', 'main')->first();
if ($existing === null) {
    $menuId = $menus->createMenu('main', 'فهرست اصلی', 'primary');
    echo "menu: {$menuId}\n";
} else {
    $menuId = (int) $existing['id'];
}
if ($menus->flatItems($menuId) === []) {
    foreach ([['خانه', '/home', 1], ['درباره ما', '/about', 2], ['فروشگاه', '/shop', 3], ['دوره‌ها', '/courses', 4], ['رزرو', '/booking', 5], ['دایرکتوری', '/directory', 6]] as [$title, $url, $order]) {
        $menus->addItem($menuId, ['title' => $title, 'type' => 'custom', 'url' => $url, 'reference_type' => null, 'reference_id' => null, 'ordering' => $order]);
    }
    echo "menu items added\n";
}

// ── Helper to create a CPT post with field meta ─────────────────
$make = function (string $type, string $title, string $slug, string $content, array $fields, string $excerpt = '') use ($posts, $db, $now): int {
    if ($posts->slugExists($type, $slug)) {
        return (int) ($db->first('SELECT id FROM posts WHERE post_type = ? AND slug = ?', [$type, $slug])['id'] ?? 0);
    }
    $post = $posts->create([
        'post_type' => $type, 'title' => $title, 'slug' => $slug, 'content' => $content,
        'excerpt' => $excerpt, 'status' => 'published', 'author_id' => 1, 'published_at' => $now,
    ]);
    foreach ($fields as $key => $value) {
        $db->insert('post_meta', ['post_id' => $post->id, 'key' => 'field:' . $key, 'value' => (string) $value]);
    }
    echo "{$type}: {$slug}\n";

    return $post->id;
};

// ── IR-Commerce: products ────────────────────────────────────────
$make('product', 'گوشی هوشمند آریا', 'phone-aria', '<p>گوشی هوشمند با دوربین حرفه‌ای و باتری قدرتمند.</p>', ['price' => '18500000', 'sale_price' => '17200000', 'sku' => 'PHN-001', 'stock' => '12'], 'گوشی هوشمند پرچم‌دار با گارانتی معتبر.');
$make('product', 'هدفون بی‌سامل صدا', 'headphone-seda', '<p>هدفون بی‌سیم با حذف نویز فعال.</p>', ['price' => '2400000', 'sale_price' => '', 'sku' => 'HDP-002', 'stock' => '30'], 'هدفون بی‌سیم با کیفیت صدای بالا.');
$make('product', 'ساعت هوشمند زمان', 'watch-zaman', '<p>ساعت هوشمند با پایش سلامت و عمر باتری هفت‌روزه.</p>', ['price' => '3900000', 'sale_price' => '3500000', 'sku' => 'WCH-003', 'stock' => '8'], 'ساعت هوشمند ورزشی.');

// ── IR-Booking: services ─────────────────────────────────────────
$make('service', 'مشاوره حضوری', 'consult-onsite', '<p>جلسه مشاوره حضوری ۴۵ دقیقه‌ای.</p>', ['duration_min' => '45', 'price' => '500000'], 'مشاوره تخصصی حضوری.');
$make('service', 'ویزیت آنلاین', 'visit-online', '<p>ویزیت آنلاین ۳۰ دقیقه‌ای با تماس تصویری.</p>', ['duration_min' => '30', 'price' => '300000'], 'ویزیت آنلاین.');

// ── IR-LMS: courses + lessons ────────────────────────────────────
$c1 = $make('course', 'آموزش مقدماتی برنامه‌نویسی وب', 'course-web-basics', '<p>در این دوره با اصول توسعه وب آشنا می‌شوید: HTML، CSS و کمی جاوااسکریپت.</p>', ['price' => '0', 'instructor' => 'سارا محمدی', 'duration_hours' => '8'], 'دوره رایگان مقدماتی توسعه وب.');
$c2 = $make('course', 'دوره جامع فروشگاه‌سازی', 'course-ecommerce', '<p>ساخت فروشگاه اینترنتی حرفه‌ای از صفر تا انتشار.</p>', ['price' => '1200000', 'instructor' => 'علی رضایی', 'duration_hours' => '20'], 'دوره پولی ساخت فروشگاه.');

$make('lesson', 'درس ۱: آشنایی با HTML', 'lesson-html', '<p>در این درس با ساختار سند HTML آشنا می‌شوید.</p>', ['course_id' => (string) $c1, 'order' => '1', 'is_free_sample' => '1']);
$make('lesson', 'درس ۲: استایل با CSS', 'lesson-css', '<p>آشنایی با انتخابگرها و جعبه‌ها در CSS.</p>', ['course_id' => (string) $c1, 'order' => '2', 'is_free_sample' => '']);
$make('lesson', 'درس ۱: معماری فروشگاه', 'lesson-ec-arch', '<p>مروری بر معماری یک فروشگاه اینترنتی.</p>', ['course_id' => (string) $c2, 'order' => '1', 'is_free_sample' => '1']);
$make('lesson', 'درس ۲: محصول و سبد خرید', 'lesson-ec-cart', '<p>پیاده‌سازی محصول و سبد خرید.</p>', ['course_id' => (string) $c2, 'order' => '2', 'is_free_sample' => '']);

// ── IR-Membership: plans ─────────────────────────────────────────
if ($db->table('ir_membership_plans')->count() === 0) {
    $db->insert('ir_membership_plans', ['title' => 'عضویت رایگان', 'slug' => 'free', 'description' => 'دسترسی پایه به محتوای عمومی.', 'price' => 0, 'period_days' => 365, 'is_active' => 1]);
    $db->insert('ir_membership_plans', ['title' => 'عضویت ویژه ماهانه', 'slug' => 'vip-monthly', 'description' => 'دسترسی کامل به دوره‌ها و محتوای ویژه.', 'price' => 199000, 'period_days' => 30, 'is_active' => 1]);
    echo "plans seeded\n";
}

// ── IR-Directory: listings ───────────────────────────────────────
$make('listing', 'کافه کتاب مرکز شهر', 'listing-cafe-ketab', '<p>کافه‌ای آرام با کتابخانه و اینترنت رایگان.</p>', ['category' => 'کافه و رستوران', 'city' => 'تهران', 'phone' => '02112345678', 'website' => '', 'address' => 'خیابان انقلاب، پلاک ۱۲'], 'کافه کتاب در مرکز شهر.');
$make('listing', 'تعمیرگاه تخصصی موبایل', 'listing-mobile-repair', '<p>تعمیر تخصصی انواع گوشی با قطعات اصلی.</p>', ['category' => 'خدمات', 'city' => 'تهران', 'phone' => '09121234567', 'website' => '', 'address' => 'بازار موبایل، طبقه دوم'], 'تعمیرگاه موبایل.');

echo "DEMO DONE\n";
