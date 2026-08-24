<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Carbon\CarbonImmutable;
use DateInvalidTimeZoneException;
use DateTimeInterface;
use DateTimeZone;
use Qubus\Support\DateTime\QubusDateTimeImmutable;

class DateTime
{
    private CarbonImmutable $dateTime;
    private string $locale = 'en';
    private DateTimeZone $timezone;

    /**
     * Returns new Datetime object.
     *
     * @param string|null $time
     * @param string|DateTimeZone|null $timezone
     * @param string|null $locale
     * @throws DateInvalidTimeZoneException
     */
    public function __construct(?string $time = null, string|DateTimeZone|null $timezone = null, ?string $locale = null)
    {
        $this->timezone = $timezone instanceof DateTimeZone
        ? $timezone
        : new DateTimeZone($timezone ?? date_default_timezone_get());
        $this->locale = $locale ?? 'en';
        $this->dateTime = QubusDateTimeImmutable::parse($time ?? 'now', $this->timezone)
            ->locale($this->locale);
    }

    public function getDateTime(): DateTimeInterface
    {
        return $this->dateTime;
    }

    /**
     * Sets the timezone.
     *
     * @param DateTimeZone|string $timezone
     * @return CarbonImmutable|false
     * @throws DateInvalidTimeZoneException
     */
    public function setTimezone(DateTimeZone|string $timezone): false|CarbonImmutable
    {
        $this->timezone = $timezone instanceof DateTimeZone
        ? $timezone
        : new DateTimeZone($timezone);

        $this->dateTime = $this->dateTime->setTimezone($this->timezone);

        return $this->dateTime;
    }

    /**
     * Returns minute in seconds.
     *
     * @return int
     */
    public static function minuteInSeconds(): int
    {
        return 60;
    }

    /**
     * Returns hour in seconds.
     *
     * @return float|int
     */
    public static function hourInSeconds(): float|int
    {
        return 60 * DateTime::minuteInSeconds();
    }

    /**
     * Returns day in seconds.
     *
     * @return float|int
     */
    public static function dayInSeconds(): float|int
    {
        return 24 * DateTime::hourInSeconds();
    }

    /**
     * Returns week in seconds.
     *
     * @return float|int
     */
    public static function weekInSeconds(): float|int
    {
        return 7 * DateTime::dayInSeconds();
    }

    /**
     * Returns month in seconds.
     *
     * @return float|int
     */
    public static function monthInSeconds(): float|int
    {
        return date('t') * DateTime::dayInSeconds();
    }

    /**
     * Returns year in seconds.
     *
     * @return float|int
     */
    public static function yearInSeconds(): float|int
    {
        return date(
            'z',
            (mktime(0, 0, 0, 12, 31, (int) date('Y'))) + 1
        ) * DateTime::dayInSeconds();
    }

    /**
     * Formats date.
     *
     * This function uses the set timezone from TriTan options.
     *
     * Example Usage:
     *
     *      $datetime = 'May 15, 2018 2:15 PM';
     *      $this->format('Y-m-d H:i:s', $datetime);
     *
     * @param string $format    Format of the date. Default is `Y-m-d H:i:s`.
     * @return string
     */
    public function format(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->dateTime->format($format);
    }

    /**
     * Format a GMT/UTC date/time
     *
     * @param string $date      Date to be formatted. Default is `now`.
     * @param string $format    Format of the date. Default is `Y-m-d H:i:s`.
     * @return string Formatted date string.
     */
    public function gmtdate(string $date = 'now', string $format = 'Y-m-d H:i:s'): string
    {
        $value = $date === 'now' ? 'now' : str_replace(['AM', 'PM'], '', $date);

        return QubusDateTimeImmutable::parse($value, new DateTimeZone('GMT'))->format($format);
    }

    /**
     * Returns the date in localized format.
     *
     * @return CarbonImmutable Returns current localized datetime.
     */
    public function locale(): CarbonImmutable
    {
        return $this->dateTime->locale($this->locale);
    }

    /**
     * Converts given date string into a different format.
     *
     * $format should be either a PHP date format string, e.g. 'U' for a Unix
     * timestamp, or 'G' for a Unix timestamp assuming that $date is GMT.
     *
     * If $translate is true, then the given date and format string will
     * be passed to $this->locale() for translation.
     *
     * @param string $format  Format of the date to return.
     * @param string $date    Date string to convert.
     * @param bool $translate Whether the return date should be translated. Default true.
     * @return string|int|bool Formatted date string or Unix timestamp. False if $date is empty.
     */
    public function db2Date(string $format, string $date, bool $translate = true): bool|int|string
    {
        if (empty($date)) {
            return false;
        }
        if ('G' === $format) {
            return strtotime($date . ' +0000');
        }
        if ('U' === $format) {
            return strtotime($date);
        }
        if ($translate) {
            return QubusDateTimeImmutable::parse($date, $this->timezone)
                ->locale($this->locale)
                ->format($format);
        }

        return QubusDateTimeImmutable::parse($date, $this->timezone)->format($format);
    }

    /**
     * Returns the current time based on specified type.
     *
     * The 'db' type will return the time in the format for database date field(s).
     * The 'timestamp' type will return the current timestamp.
     * Other strings will be interpreted as PHP date formats (e.g. 'Y-m-d H:i:s').
     *
     * If $gmt is set to either '1' or 'true', then both types will use GMT time.
     * If $gmt is false, the output is adjusted with the GMT offset based on General Settings.
     *
     * @param string $type Type of time to return. Accepts 'db', 'timestamp', or PHP date
     *                     format string (e.g. 'Y-m-d').
     * @param bool $gmt    Optional. Whether to use GMT timezone. Default false.
     * @return int|string Integer if $type is 'timestamp', string otherwise.
     */
    public function current(string $type, bool $gmt = false): int|string
    {
        if ('timestamp' === $type || 'U' === $type) {
            $now = QubusDateTimeImmutable::now($gmt ? new DateTimeZone('GMT') : $this->timezone);

            return $gmt ? $now->getTimestamp() : $now->getTimestamp() + $now->offset;
        }
        if ('db' === $type) {
            $type = 'Y-m-d H:i:s';
        }
        $timezone = $gmt ? new DateTimeZone('GMT') : $this->timezone;
        $datetime = QubusDateTimeImmutable::now($timezone)->locale($this->locale);

        return $datetime->format($type);
    }

    /**
     * Converts timestamp to localized human-readable date.
     *
     * @param string $format PHP date format string (e.g. 'Y-m-d').
     * @param int $timestamp Timestamp to convert.
     * @return string Localized human readable date.
     */
    public function timestampToDate(string $format, int $timestamp): string
    {
        return QubusDateTimeImmutable::createFromTimestamp($timestamp, $this->timezone)
            ->locale($this->locale)
            ->format($format);
    }
}
