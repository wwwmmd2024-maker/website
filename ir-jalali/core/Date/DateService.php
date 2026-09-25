<?php

declare(strict_types=1);

namespace IRJalali\Core\Date;

/**
 * Single entry point for ALL date/time operations in the platform.
 * Admin, scheduler, booking, publishing and logs must use this —
 * never raw date()/time() for display.
 */
final class DateService
{
    /** @var array<string, CalendarInterface> */
    private array $calendars = [];

    public function __construct(
        private string $active = 'jalali',
        private readonly string $timezone = 'Asia/Tehran',
    ) {
        $this->calendars = [
            'jalali' => new JalaliCalendar(),
            'gregorian' => new GregorianCalendar(),
        ];
    }

    public function registerCalendar(CalendarInterface $calendar): void
    {
        $this->calendars[$calendar->key()] = $calendar;
    }

    public function use(string $calendar): self
    {
        if (isset($this->calendars[$calendar])) {
            $this->active = $calendar;
        }

        return $this;
    }

    public function active(): string
    {
        return $this->active;
    }

    public function calendar(): CalendarInterface
    {
        return $this->calendars[$this->active] ?? $this->calendars['gregorian'];
    }

    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone($this->timezone));
    }

    /** Storage format (Gregorian, app timezone). */
    public function nowForStorage(): string
    {
        return $this->now()->format('Y-m-d H:i:s');
    }

    public function parse(string $datetime): \DateTimeImmutable
    {
        $dt = new \DateTimeImmutable($datetime, new \DateTimeZone($this->timezone));

        return $dt->setTimezone(new \DateTimeZone($this->timezone));
    }

    public function format(string $datetime, string $format = 'Y/m/d H:i'): string
    {
        return $this->calendar()->format($this->parse($datetime), $format);
    }

    public function formatDate(string $datetime): string
    {
        return $this->format($datetime, 'Y/m/d');
    }

    public function formatFull(string $datetime): string
    {
        return $this->format($datetime, 'l j F Y — H:i');
    }

    public function toPersianDigits(string $input): string
    {
        return strtr($input, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    public function diffForHumans(string $datetime, ?string $locale = null): string
    {
        $locale ??= $this->active === 'jalali' ? 'fa' : 'en';
        $target = $this->parse($datetime)->getTimestamp();
        $diff = $this->now()->getTimestamp() - $target;
        $future = $diff < 0;
        $diff = abs($diff);

        if ($locale === 'fa') {
            $t = fn(int $n, string $u) => $this->toPersianDigits((string) $n) . ' ' . $u . ($future ? ' بعد' : ' پیش');
            if ($diff < 60) {
                return 'لحظاتی پیش';
            }
            if ($diff < 3600) {
                return $t(intdiv($diff, 60), 'دقیقه');
            }
            if ($diff < 86400) {
                return $t(intdiv($diff, 3600), 'ساعت');
            }
            if ($diff < 2592000) {
                return $t(intdiv($diff, 86400), 'روز');
            }
            if ($diff < 31536000) {
                return $t(intdiv($diff, 2592000), 'ماه');
            }

            return $t(intdiv($diff, 31536000), 'سال');
        }

        $t = fn(int $n, string $u) => $n . ' ' . $u . ($n > 1 ? 's' : '') . ($future ? ' from now' : ' ago');
        if ($diff < 60) {
            return 'just now';
        }
        if ($diff < 3600) {
            return $t(intdiv($diff, 60), 'minute');
        }
        if ($diff < 86400) {
            return $t(intdiv($diff, 3600), 'hour');
        }
        if ($diff < 2592000) {
            return $t(intdiv($diff, 86400), 'day');
        }

        return $t(intdiv($diff, 2592000), 'month');
    }
}
