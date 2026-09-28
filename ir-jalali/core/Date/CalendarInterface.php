<?php

declare(strict_types=1);

namespace IRJalali\Core\Date;

/**
 * Calendar abstraction. Storage is ALWAYS Gregorian UTC-ish datetime strings;
 * presentation converts through the active calendar (jalali | gregorian).
 * Future calendars (hijri, ...) implement this same contract.
 */
interface CalendarInterface
{
    public function key(): string;

    /** @return array{y: int, m: int, d: int} */
    public function fromDateTime(\DateTimeImmutable $date): array;

    public function toDateTime(int $year, int $month, int $day, int $hour = 0, int $minute = 0, int $second = 0): \DateTimeImmutable;

    public function format(\DateTimeImmutable $date, string $format): string;

    public function isLeapYear(int $year): bool;

    public function monthLength(int $year, int $month): int;

    /** @return list<string> */
    public function monthNames(): array;

    /** @return list<string> */
    public function weekdayNames(): array;
}
