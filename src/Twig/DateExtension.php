<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Nowo\UiKitBundle\Date\DateFormatterInterface;
use Nowo\UiKitBundle\Date\IsoDateFormatter;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\TwigFilter;

/**
 * 2.75: dates for people in kit templates, through the host's {@see DateFormatterInterface}.
 *
 * - `{{ run.at|nowo_date }}`, `{{ run.at|nowo_date('date') }}`, `{{ run.at|nowo_date('datetime', true) }}`: the text
 *   (escaped by autoescape like any string);
 * - `{{ run.at|nowo_time_tag }}`, `{{ run.at|nowo_time_tag('datetime', false, {class: 'muted'}) }}`: the markup
 *   `<time datetime="2026-11-27T16:14:00+00:00" title="2026-11-27 16:14 UTC">2026-11-27 16:14</time>`. The kinds with a
 *   time carry the full ISO-8601 instant and, in `title`, the date and time with the zone; the day kinds carry the day
 *   (`2026-11-27`) or month (`2026-11`), no title, and `data-no-relative` (a day has no time to count from). Every
 *   value is HTML-escaped here; the filter is `is_safe` html.
 *
 * Both read the value like Twig's `|date`: a `DateTimeInterface`, a timestamp or a date string, moved to Twig's time
 * zone; null and '' print nothing (never "now"). Stateless (the formatter is the host's service), safe in workers.
 */
final class DateExtension extends AbstractExtension
{
    /** Valid HTML attribute name of an extra attribute of `nowo_time_tag`. */
    private const ATTRIBUTE_NAME = '/^[a-zA-Z_:][a-zA-Z0-9_:.\-]*$/';

    public function __construct(
        private readonly DateFormatterInterface $formatter = new IsoDateFormatter(),
    ) {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('nowo_date', $this->date(...), ['needs_environment' => true]),
            new TwigFilter('nowo_time_tag', $this->timeTag(...), ['needs_environment' => true, 'is_safe' => ['html']]),
        ];
    }

    /**
     * The date as a person reads it; '' for null or ''.
     *
     * @param string $kind one of {@see DateFormatterInterface::KINDS}
     * @param bool   $zone append the zone's abbreviation
     */
    public function date(Environment $env, mixed $date, string $kind = DateFormatterInterface::DATETIME, bool $zone = false): string
    {
        $date = self::dateOf($env, $date);

        return null === $date ? '' : $this->formatter->format($date, $kind, $zone);
    }

    /**
     * `<time datetime="ISO-8601" title="…">text</time>`; '' for null or ''.
     *
     * @param string                     $kind       one of {@see DateFormatterInterface::KINDS}
     * @param bool                       $zone       append the zone's abbreviation to the text
     * @param array<string, scalar|null> $attributes extra attributes (`class`, `data-testid`…); null skips one; they never
     *                                               replace `datetime`, `title` or `data-no-relative`
     */
    public function timeTag(Environment $env, mixed $date, string $kind = DateFormatterInterface::DATETIME, bool $zone = false, array $attributes = []): string
    {
        $date = self::dateOf($env, $date);
        if (null === $date) {
            return '';
        }
        $text = $this->formatter->format($date, $kind, $zone);
        $own = match ($kind) {
            DateFormatterInterface::DATE => ['datetime' => $date->format('Y-m-d'), 'data-no-relative' => ''],
            DateFormatterInterface::MONTH => ['datetime' => $date->format('Y-m'), 'data-no-relative' => ''],
            default => [
                'datetime' => $date->format(DateTimeInterface::ATOM),
                'title' => $this->formatter->format($date, DateFormatterInterface::DATETIME_SECONDS === $kind ? $kind : DateFormatterInterface::DATETIME, true),
            ],
        };
        $html = '<time';
        foreach ($own + $attributes as $name => $value) {
            if (null === $value) {
                continue;
            }
            if (1 !== preg_match(self::ATTRIBUTE_NAME, (string) $name)) {
                throw new InvalidArgumentException(\sprintf('nowo_time_tag: invalid attribute name "%s".', $name));
            }
            $html .= ' '.$name.'="'.self::escape(\is_bool($value) ? ($value ? 'true' : 'false') : (string) $value).'"';
        }

        return $html.'>'.self::escape($text).'</time>';
    }

    /**
     * Like Twig's `|date`: a date, a timestamp (int or digit string) or a date string, in Twig's time zone (an
     * immutable copy, the caller's object is never changed); null and '' are no date.
     */
    private static function dateOf(Environment $env, mixed $date): ?DateTimeImmutable
    {
        if (null === $date || '' === $date) {
            return null;
        }
        $zone = $env->getExtension(CoreExtension::class)->getTimezone();
        if ($date instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($date)->setTimezone($zone);
        }
        if (\is_int($date) || (\is_string($date) && 1 === preg_match('/^-?\d+$/', $date))) {
            return (new DateTimeImmutable('@'.$date))->setTimezone($zone);
        }
        if (!\is_string($date)) {
            throw new InvalidArgumentException(\sprintf('nowo_date / nowo_time_tag: expected a date, a timestamp or a date string, got %s.', get_debug_type($date)));
        }

        return (new DateTimeImmutable($date, $zone))->setTimezone($zone);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
