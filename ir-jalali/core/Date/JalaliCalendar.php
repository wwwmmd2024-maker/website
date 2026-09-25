<?php

declare(strict_types=1);

namespace IRJalali\Core\Date;

final class JalaliCalendar implements CalendarInterface
{
    public function key(): string
    {
        return 'jalali';
    }

    public function fromDateTime(\DateTimeImmutable $date): array
    {
        $j = JalaliConverter::toJalali(
            (int) $date->format('Y'),
            (int) $date->format('n'),
            (int) $date->format('j')
        );

        return ['y' => $j['jy'], 'm' => $j['jm'], 'd' => $j['jd']];
    }

    public function toDateTime(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0): \DateTimeImmutable
    {
        $g = JalaliConverter::toGregorian($year, $month, $day);

        return new \DateTimeImmutable(sprintf(
            '%04d-%02d-%02d %02d:%02d:%02d',
            $g['gy'],
            $g['gm'],
            $g['gd'],
            $hour,
            $minute,
            $second
        ));
    }

    /**
     * Supports: Y (year) m (month 2-digit) n (month) d (day 2-digit) j (day)
     * F (month name) l (weekday name) H i s + any other chars passed through.
     */
    public function format(\DateTimeImmutable $date, string $format): string
    {
        $j = $this->fromDateTime($date);
        $weekday = (int) $date->format('w'); // 0=Sunday (یکشنبه)
        // Persian week starts Saturday; map PHP w (Sun=0..Sat=6) to (شنبه=0..جمعه=6).
        $faWeekday = ($weekday + 1) % 7;

        $replacements = [
            'Y' => (string) $j['y'],
            'm' => str_pad((string) $j['m'], 2, '0', STR_PAD_LEFT),
            'n' => (string) $j['m'],
            'd' => str_pad((string) $j['d'], 2, '0', STR_PAD_LEFT),
            'j' => (string) $j['d'],
            'F' => $this->monthNames()[$j['m'] - 1],
            'l' => $this->weekdayNames()[$faWeekday],
            'H' => $date->format('H'),
            'i' => $date->format('i'),
            's' => $date->format('s'),
        ];

        $out = '';
        $len = strlen($format);
        for ($i = 0; $i < $len; $i++) {
            $char = $format[$i];
            if ($char === '\\' && $i + 1 < $len) {
                $out .= $format[++$i];
                continue;
            }
            $out .= $replacements[$char] ?? $char;
        }

        return $out;
    }

    public function isLeapYear(int $year): bool
    {
        return JalaliConverter::isLeapJalaliYear($year);
    }

    public function monthLength(int $year, int $month): int
    {
        return JalaliConverter::jalaaliMonthLength($year, $month);
    }

    public function monthNames(): array
    {
        return ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];
    }

    public function weekdayNames(): array
    {
        return ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];
    }
}
