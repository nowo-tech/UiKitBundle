<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Date;

use DateTimeInterface;
use InvalidArgumentException;

/**
 * 2.75: the default {@see DateFormatterInterface}, the fixed ISO-style output the kits printed until 2.74, so a host
 * without its own formatter sees no change: `2026-11-27`, `2026-11-27 16:14`, `2026-11-27 16:14:05`, `16:14`,
 * `2026-11`, plus ` UTC` (the zone's abbreviation) when asked. The date is printed in the zone it carries.
 *
 * Stateless and pure, safe in long-running workers.
 */
final class IsoDateFormatter implements DateFormatterInterface
{
    /** PHP date format of each kind. */
    public const FORMATS = [
        self::DATE => 'Y-m-d',
        self::DATETIME => 'Y-m-d H:i',
        self::DATETIME_SECONDS => 'Y-m-d H:i:s',
        self::TIME => 'H:i',
        self::MONTH => 'Y-m',
    ];

    public function format(DateTimeInterface $date, string $kind = self::DATETIME, bool $zone = false): string
    {
        return $date->format(self::formatOf($kind).($zone ? ' T' : ''));
    }

    /**
     * The PHP date format of a kind.
     *
     * @throws InvalidArgumentException for a kind outside {@see DateFormatterInterface::KINDS}
     */
    public static function formatOf(string $kind): string
    {
        return self::FORMATS[$kind] ?? throw new InvalidArgumentException(\sprintf('Unknown date kind "%s" (expected one of: %s).', $kind, implode(', ', self::KINDS)));
    }
}
