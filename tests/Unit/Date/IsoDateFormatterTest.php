<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Date;

use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Nowo\UiKitBundle\Date\DateFormatterInterface;
use Nowo\UiKitBundle\Date\IsoDateFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class IsoDateFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, string}>
     */
    public static function kinds(): iterable
    {
        yield 'date' => ['date', false, '2026-11-27'];
        yield 'datetime' => ['datetime', false, '2026-11-27 16:14'];
        yield 'datetime_seconds' => ['datetime_seconds', false, '2026-11-27 16:14:05'];
        yield 'time' => ['time', false, '16:14'];
        yield 'month' => ['month', false, '2026-11'];
        yield 'datetime with zone' => ['datetime', true, '2026-11-27 16:14 UTC'];
        yield 'date with zone' => ['date', true, '2026-11-27 UTC'];
    }

    #[DataProvider('kinds')]
    public function testEachKindKeepsTheIsoOutput(string $kind, bool $zone, string $expected): void
    {
        $date = new DateTimeImmutable('2026-11-27 16:14:05', new DateTimeZone('UTC'));

        self::assertSame($expected, (new IsoDateFormatter())->format($date, $kind, $zone));
    }

    public function testTheDefaultKindIsDatetimeWithoutZone(): void
    {
        self::assertSame('2026-11-27 16:14', (new IsoDateFormatter())->format(new DateTimeImmutable('2026-11-27 16:14:05', new DateTimeZone('UTC'))));
    }

    public function testTheDateIsPrintedInItsOwnZone(): void
    {
        $formatter = new IsoDateFormatter();
        $date = new DateTime('2026-11-27 23:30:00', new DateTimeZone('Europe/Madrid'));

        self::assertSame('2026-11-27 23:30 CET', $formatter->format($date, 'datetime', true));
        self::assertSame('2026-11-27', $formatter->format($date, 'date'));
        self::assertSame('Europe/Madrid', $date->getTimezone()->getName(), 'the caller\'s object is not changed');
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown date kind "Y-m-d" (expected one of: date, datetime, datetime_seconds, time, month).');

        (new IsoDateFormatter())->format(new DateTimeImmutable(), 'Y-m-d');
    }

    public function testEveryKindHasAFormatAndTheFormatterIsStateless(): void
    {
        self::assertSame(DateFormatterInterface::KINDS, array_keys(IsoDateFormatter::FORMATS));
        self::assertSame([], (new ReflectionClass(IsoDateFormatter::class))->getProperties(), 'no state kept between requests (worker mode)');
    }
}
