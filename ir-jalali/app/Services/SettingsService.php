<?php

declare(strict_types=1);

namespace IRJalali\App\Services;

use IRJalali\App\Repositories\OptionRepository;

/**
 * Typed access to the settings surface (backed by options table).
 */
final class SettingsService
{
    public function __construct(private readonly OptionRepository $options)
    {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return [
            'site_title' => $this->options->get('site_title', 'سایت من'),
            'tagline' => $this->options->get('tagline', ''),
            'language' => $this->options->get('language', 'fa_IR'),
            'timezone' => $this->options->get('timezone', 'Asia/Tehran'),
            'website_mode' => $this->options->get('website_mode', 'general'),
            'website_type' => $this->options->get('website_type', 'custom'),
            'setup_completed' => (bool) $this->options->get('setup_completed', false),
        ];
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): void
    {
        $allowed = ['site_title', 'tagline', 'language', 'timezone', 'website_mode'];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $this->options->set($key, (string) $data[$key]);
            }
        }
    }

    /** @return list<array{value: string, label: string}> */
    public static function websiteModes(): array
    {
        return [
            ['value' => 'general', 'label' => 'عمومی'],
            ['value' => 'business', 'label' => 'کسب‌وکار'],
            ['value' => 'blog', 'label' => 'وبلاگ'],
            ['value' => 'magazine', 'label' => 'مجله'],
            ['value' => 'portfolio', 'label' => 'نمونه‌کار'],
            ['value' => 'booking', 'label' => 'رزرواسیون'],
            ['value' => 'membership', 'label' => 'عضویتی'],
            ['value' => 'education', 'label' => 'آموزشی'],
            ['value' => 'ecommerce', 'label' => 'فروشگاهی'],
            ['value' => 'marketplace', 'label' => 'مارکت‌پلیس'],
            ['value' => 'directory', 'label' => 'دایرکتوری'],
            ['value' => 'custom', 'label' => 'سفارشی'],
        ];
    }
}
