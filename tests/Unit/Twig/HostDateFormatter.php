<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Twig;

use DateTimeInterface;
use Nowo\UiKitBundle\Date\DateFormatterInterface;

/** A host formatter of {@see DateExtensionTest}: the container alias replaces the kit's default. */
final class HostDateFormatter implements DateFormatterInterface
{
    public function format(DateTimeInterface $date, string $kind = self::DATETIME, bool $zone = false): string
    {
        return 'host:'.$kind;
    }
}
