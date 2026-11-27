<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Date;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * 2.75: dates for people. Every date a person reads on a kit page (a summary line, a "last run" column, an expiry
 * warning) goes through this contract; machine values (`datetime` attributes, `data-sort-value`, keys, URL parameters,
 * comparisons, CSV, logs) keep a fixed format.
 *
 * The kit ships {@see IsoDateFormatter} (the fixed ISO-style output of the kits until 2.74), aliased to this interface.
 * A host whose people choose their date order, clock or time zone aliases the interface to its own service; every kit
 * page that formats through it follows those preferences at once.
 *
 * Kinds:
 * - `date`: the calendar day;
 * - `datetime`: day, hours and minutes;
 * - `datetime_seconds`: day and time with seconds;
 * - `time`: hours and minutes;
 * - `month`: month and year.
 *
 * Time zones: the kinds with a time (`datetime`, `datetime_seconds`, `time`) are instants, and an implementation may
 * show them in the reader's zone. The day kinds (`date`, `month`) are printed in the zone `$date` carries, never
 * moved: a value that is a calendar day and not an instant (an expiry date stored as midnight UTC) keeps its day for
 * every reader. A caller that shows the local day of an instant converts it first (the `nowo_date` and `nowo_time_tag`
 * Twig filters do, to Twig's time zone, like `|date`).
 *
 * Implementations are stateless or read the reader per call (worker mode: one service for every request); they never
 * keep the last reader.
 */
interface DateFormatterInterface
{
    public const DATE = 'date';
    public const DATETIME = 'datetime';
    public const DATETIME_SECONDS = 'datetime_seconds';
    public const TIME = 'time';
    public const MONTH = 'month';

    /** Every kind {@see self::format()} accepts. */
    public const KINDS = [self::DATE, self::DATETIME, self::DATETIME_SECONDS, self::TIME, self::MONTH];

    /**
     * The date as a person reads it.
     *
     * @param string $kind one of {@see self::KINDS}
     * @param bool   $zone append the time zone's abbreviation (`UTC`, `CET`), for a value read away from its context
     *
     * @throws InvalidArgumentException for a kind outside {@see self::KINDS}
     */
    public function format(DateTimeInterface $date, string $kind = self::DATETIME, bool $zone = false): string;
}
