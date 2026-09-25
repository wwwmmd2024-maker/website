<?php

declare(strict_types=1);

namespace IRJalali\Core\Date;

final class GregorianCalendar implements CalendarInterface
{
    public function key(): string
    {
        return 'gregorian';
    }

    public function fromDateTime(\DateTimeImmutable $date): array
    {
        return [
            'y' => (int) $date->format('Y'),
            'm' => (int) $date->format('n'),
            'd' => (int) $date->format('j'),
        ];
    }

    public function toDateTime(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0): \DateTimeImmutable
    {
        return new \DateTimeImmutable(sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minute, $second));
    }

    public function format(\DateTimeImmutable $date, string $format): string
    {
        return $date->format($format);
    }

    public function isLeapYear(int $year): bool
    {
        return ($year % 4 === 0 && $year % 100 !== 0) || $year % 400 === 0;
    }

    public function monthLength(int $year, int $month): int
    {
        return (int) cal_days_in_month(CAL_GREGORIAN, $month, $year);
    }

    public function monthNames(): array
    {
        return ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    }

    public function weekdayNames(): array
    {
        return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    }
}
