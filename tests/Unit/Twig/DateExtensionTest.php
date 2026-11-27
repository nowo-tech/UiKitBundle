<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Twig;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Nowo\UiKitBundle\Date\DateFormatterInterface;
use Nowo\UiKitBundle\Date\IsoDateFormatter;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Twig\DateExtension;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\CoreExtension;
use Twig\Loader\ArrayLoader;

final class DateExtensionTest extends TestCase
{
    private const AT = '2026-11-27T16:14:05+00:00';

    /**
     * @param array<string, string> $templates
     */
    private static function twig(array $templates, ?DateFormatterInterface $formatter = null, string $zone = 'UTC'): Environment
    {
        $twig = new Environment(new ArrayLoader($templates));
        $twig->getExtension(CoreExtension::class)->setTimezone($zone);
        $twig->addExtension(null === $formatter ? new DateExtension() : new DateExtension($formatter));

        return $twig;
    }

    public function testNowoDateKeepsTheIsoOutputByDefault(): void
    {
        $twig = self::twig([
            'default' => '{{ at|nowo_date }}',
            'date' => '{{ at|nowo_date("date") }}',
            'seconds' => '{{ at|nowo_date("datetime_seconds", true) }}',
            'time' => '{{ at|nowo_date("time") }}',
            'month' => '{{ at|nowo_date("month") }}',
        ]);
        $at = new DateTimeImmutable(self::AT);

        self::assertSame('2026-11-27 16:14', $twig->render('default', ['at' => $at]));
        self::assertSame('2026-11-27', $twig->render('date', ['at' => $at]));
        self::assertSame('2026-11-27 16:14:05 UTC', $twig->render('seconds', ['at' => $at]));
        self::assertSame('16:14', $twig->render('time', ['at' => $at]));
        self::assertSame('2026-11', $twig->render('month', ['at' => $at]));
    }

    public function testValuesAreReadLikeTwigDateInTwigsZone(): void
    {
        $twig = self::twig(['t' => '{{ at|nowo_date("datetime", true) }}'], null, 'Europe/Madrid');
        $mutable = new DateTime(self::AT);

        self::assertSame('2026-11-27 17:14 CET', $twig->render('t', ['at' => $mutable]));
        self::assertSame('+00:00', $mutable->format('P'), 'the caller\'s object is not changed');
        self::assertSame('2026-11-27 17:14 CET', $twig->render('t', ['at' => self::AT]));
        self::assertSame('2026-11-27 17:14 CET', $twig->render('t', ['at' => (new DateTimeImmutable(self::AT))->getTimestamp()]));
        self::assertSame('2026-11-27 17:14 CET', $twig->render('t', ['at' => (string) (new DateTimeImmutable(self::AT))->getTimestamp()]));
        self::assertSame('2026-11-27 16:14 CET', $twig->render('t', ['at' => '2026-11-27 16:14']), 'a string without zone is in Twig\'s zone');
    }

    public function testNullAndEmptyPrintNothing(): void
    {
        $twig = self::twig(['d' => '[{{ at|nowo_date }}]', 't' => '[{{ at|nowo_time_tag }}]']);

        foreach ([null, ''] as $empty) {
            self::assertSame('[]', $twig->render('d', ['at' => $empty]));
            self::assertSame('[]', $twig->render('t', ['at' => $empty]));
        }
    }

    public function testOtherValuesAreRefused(): void
    {
        $twig = self::twig(['d' => '{{ at|nowo_date }}']);

        try {
            $twig->render('d', ['at' => new stdClass()]);
            self::fail('expected an error');
        } catch (RuntimeError $e) {
            self::assertInstanceOf(InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $twig = self::twig(['d' => '{{ at|nowo_date("Y-m-d") }}']);

        $this->expectException(RuntimeError::class);
        $twig->render('d', ['at' => new DateTimeImmutable(self::AT)]);
    }

    public function testTimeTagOfAnInstant(): void
    {
        $twig = self::twig([
            'default' => '{{ at|nowo_time_tag }}',
            'zone' => '{{ at|nowo_time_tag("time", true) }}',
            'seconds' => '{{ at|nowo_time_tag("datetime_seconds") }}',
        ], null, 'Europe/Madrid');
        $at = new DateTimeImmutable(self::AT);

        self::assertSame('<time datetime="2026-11-27T17:14:05+01:00" title="2026-11-27 17:14 CET">2026-11-27 17:14</time>', $twig->render('default', ['at' => $at]));
        self::assertSame('<time datetime="2026-11-27T17:14:05+01:00" title="2026-11-27 17:14 CET">17:14 CET</time>', $twig->render('zone', ['at' => $at]));
        self::assertSame('<time datetime="2026-11-27T17:14:05+01:00" title="2026-11-27 17:14:05 CET">2026-11-27 17:14:05</time>', $twig->render('seconds', ['at' => $at]));
    }

    public function testTimeTagOfADayOrMonth(): void
    {
        $twig = self::twig([
            'date' => '{{ at|nowo_time_tag("date") }}',
            'month' => '{{ at|nowo_time_tag("month") }}',
        ]);
        $at = new DateTimeImmutable(self::AT);

        self::assertSame('<time datetime="2026-11-27" data-no-relative="">2026-11-27</time>', $twig->render('date', ['at' => $at]));
        self::assertSame('<time datetime="2026-11" data-no-relative="">2026-11</time>', $twig->render('month', ['at' => $at]));
    }

    public function testTimeTagEscapesEveryValueAndKeepsItsOwnAttributes(): void
    {
        $twig = self::twig(['t' => '{{ at|nowo_time_tag("datetime", false, attrs) }}'], new class implements DateFormatterInterface {
            public function format(DateTimeInterface $date, string $kind = self::DATETIME, bool $zone = false): string
            {
                return '<b>"'.$kind.'" & \'x\'</b>'.($zone ? ' z' : '');
            }
        });

        $html = $twig->render('t', ['at' => new DateTimeImmutable(self::AT), 'attrs' => [
            'class' => 'a" onmouseover="x',
            'datetime' => 'evil',
            'data-testid' => 'when',
            'data-skip' => null,
            'data-flag' => true,
            'data-count' => 3,
        ]]);

        self::assertSame(
            '<time datetime="2026-11-27T16:14:05+00:00" title="&lt;b&gt;&quot;datetime&quot; &amp; &apos;x&apos;&lt;/b&gt; z" class="a&quot; onmouseover=&quot;x" data-testid="when" data-flag="true" data-count="3">&lt;b&gt;&quot;datetime&quot; &amp; &apos;x&apos;&lt;/b&gt;</time>',
            $html,
        );
    }

    public function testTimeTagRefusesAnInvalidAttributeName(): void
    {
        $twig = self::twig(['t' => '{{ at|nowo_time_tag("datetime", false, {"x onclick": "y"}) }}']);

        try {
            $twig->render('t', ['at' => new DateTimeImmutable(self::AT)]);
            self::fail('expected an error');
        } catch (RuntimeError $e) {
            self::assertInstanceOf(InvalidArgumentException::class, $e->getPrevious());
        }
    }

    public function testTheTimeTagIsNotEscapedTwiceAndNowoDateIsEscaped(): void
    {
        $twig = self::twig(['t' => '{{ at|nowo_time_tag }}|{{ at|nowo_date }}'], new class implements DateFormatterInterface {
            public function format(DateTimeInterface $date, string $kind = self::DATETIME, bool $zone = false): string
            {
                return 'a<b';
            }
        });

        self::assertSame('<time datetime="2026-11-27T16:14:05+00:00" title="a&lt;b">a&lt;b</time>|a&lt;b', $twig->render('t', ['at' => new DateTimeImmutable(self::AT)]));
    }

    public function testTheHostFormatterReplacesTheDefault(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([], $container);

        self::assertSame(IsoDateFormatter::class, $container->getDefinition('nowo_ui_kit.date_formatter')->getClass());
        self::assertSame('nowo_ui_kit.date_formatter', (string) $container->getAlias(DateFormatterInterface::class));
        $extension = $container->getDefinition('nowo_ui_kit.date_twig_extension');
        self::assertSame(DateExtension::class, $extension->getClass());
        self::assertTrue($extension->hasTag('twig.extension'));
        self::assertSame(DateFormatterInterface::class, (string) $extension->getArgument(0));

        $container->register('app.reader_dates', HostDateFormatter::class);
        $container->setAlias(DateFormatterInterface::class, 'app.reader_dates');
        $container->getDefinition('nowo_ui_kit.date_twig_extension')->setPublic(true);
        $container->compile();

        $twig = new Environment(new ArrayLoader(['t' => '{{ at|nowo_date("date") }}']));
        $extension = $container->get('nowo_ui_kit.date_twig_extension');
        self::assertInstanceOf(DateExtension::class, $extension);
        $twig->addExtension($extension);
        self::assertSame('host:date', $twig->render('t', ['at' => new DateTimeImmutable(self::AT)]));
    }

    public function testRepeatedCallsShareNothing(): void
    {
        $twig = self::twig(['t' => '{{ at|nowo_date("datetime", true) }}']);
        $core = $twig->getExtension(CoreExtension::class);

        for ($i = 0; $i < 3; ++$i) {
            $core->setTimezone(0 === $i % 2 ? 'UTC' : 'Asia/Tokyo');
            self::assertSame(0 === $i % 2 ? '2026-11-27 16:14 UTC' : '2026-11-28 01:14 JST', $twig->render('t', ['at' => self::AT]));
        }
    }
}
