<?php

declare(strict_types=1);

namespace IRJalali\App\Services;

use IRJalali\App\Repositories\OptionRepository;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Kernel\Application;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\Plugins\PluginManager;

/**
 * Website Mode System (Master Prompt Part 1 §6 / Part 2 §46).
 *
 * A mode only toggles which modules/plugins are surfaced and active — it NEVER
 * deletes data. Disabling "E-Commerce" keeps products/orders rows intact and
 * only deactivates the module.
 */
final class WebsiteModeService
{
    /** Mode slug => [label, description, recommended plugin slugs]. */
    public const MODES = [
        'general' => ['عمومی', 'سایت عمومی بدون ماژول خاص.', []],
        'business' => ['کسب‌وکار', 'سایت شرکتی با فرم، سئو و کش.', ['ir-seo', 'ir-cache', 'ir-smtp']],
        'blog' => ['وبلاگ', 'محتوا‌محور با خبرنامه و سئو.', ['ir-seo', 'ir-analytics']],
        'magazine' => ['خبری/مجله‌ای', 'خبرگزاری با جستجو، سئو و تحلیل.', ['ir-seo', 'ir-analytics', 'ir-cache']],
        'portfolio' => ['نمونه‌کار', 'نمایش پروژه‌ها و خدمات.', ['ir-seo']],
        'booking' => ['رزرواسیون', 'خدمات، کارکنان و وقت‌دهی آنلاین.', ['ir-booking', 'ir-smtp', 'ir-seo']],
        'membership' => ['عضویت', 'ثبت‌نام، اشتراک و محتوای محافظت‌شده.', ['ir-membership', 'ir-smtp']],
        'education' => ['آموزشی (LMS)', 'دوره، درس، ثبت‌نام و آزمون.', ['ir-lms', 'ir-membership', 'ir-smtp']],
        'ecommerce' => ['فروشگاهی', 'محصول، سبد خرید، سفارش و پرداخت.', ['ir-commerce', 'ir-seo', 'ir-smtp']],
        'marketplace' => ['مارکت‌پلیس', 'فروشگاه چندفروشندگی روی ماژول تجارت.', ['ir-commerce', 'ir-membership', 'ir-seo']],
        'directory' => ['دایرکتوری', 'آگهی‌ها، دسته‌بندی، مکان و جستجو.', ['ir-directory', 'ir-seo']],
        'custom' => ['سفارشی', 'ترکیب دلخواه ماژول‌ها.', []],
    ];

    public function __construct(
        private readonly OptionRepository $options,
        private readonly Database $db,
        private readonly Logger $logger,
    ) {
    }

    public function current(): string
    {
        $mode = (string) $this->options->get('website_mode', 'general');

        return array_key_exists($mode, self::MODES) ? $mode : 'general';
    }

    /** @return array{label: string, description: string, plugins: list<string>} */
    public function describe(string $mode): array
    {
        $row = self::MODES[$mode] ?? self::MODES['general'];

        return ['label' => $row[0], 'description' => $row[1], 'plugins' => $row[2]];
    }

    /**
     * Apply a mode: persist it and (optionally) activate the recommended
     * plugins. Data is never removed. Returns per-plugin results.
     *
     * @return list<array{slug: string, ok: bool, message: string}>
     */
    public function apply(string $mode, bool $activatePlugins = true, ?int $actorId = null): array
    {
        if (!array_key_exists($mode, self::MODES)) {
            throw new \InvalidArgumentException("Unknown website mode [{$mode}].");
        }

        $before = $this->current();
        $this->options->set('website_mode', $mode);
        $this->options->set('website_mode_changed_at', date('Y-m-d H:i:s'));

        $results = [];
        if ($activatePlugins) {
            /** @var PluginManager $plugins */
            $plugins = Application::get()->make(PluginManager::class);
            $plugins->discover();
            foreach ($this->describe($mode)['plugins'] as $slug) {
                try {
                    $plugin = $plugins->plugin($slug);
                    if ($plugin === null) {
                        $results[] = ['slug' => $slug, 'ok' => false, 'message' => 'افزونه نصب نیست (از بخش افزونه‌ها/مارکت‌پلیس نصب کنید).'];
                        continue;
                    }
                    if ($plugin->isActive()) {
                        $results[] = ['slug' => $slug, 'ok' => true, 'message' => 'قبلاً فعال بود.'];
                        continue;
                    }
                    $plugins->activate($slug, $plugin->capabilities);
                    $results[] = ['slug' => $slug, 'ok' => true, 'message' => 'فعال شد.'];
                } catch (\Throwable $e) {
                    $results[] = ['slug' => $slug, 'ok' => false, 'message' => $e->getMessage()];
                }
            }
        }

        $this->logger->channel('admin')->info('Website mode changed', [
            'from' => $before, 'to' => $mode, 'actor' => $actorId,
        ]);

        return $results;
    }

    /** Plugin usage matrix for the modes admin screen. */
    public function pluginStatus(): array
    {
        try {
            $rows = $this->db->table('plugins')->orderBy('slug')->get();
        } catch (\Throwable) {
            return [];
        }
        $status = [];
        foreach ($rows as $row) {
            $status[$row['slug']] = ($row['status'] ?? 'inactive') === 'active';
        }

        return $status;
    }
}
