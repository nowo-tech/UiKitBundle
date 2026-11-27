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
 * Both read the value like Twig's `|date`: a `DateTimeInterface`, a timestamp (int, float or digit string) or a date
 * string; null and '' print nothing (never "now"). The kinds with a time are instants and are moved to Twig's time
 * zone. The day kinds (`date`, `month`) never move a `DateTimeInterface`: a calendar day stored as midnight UTC keeps
 * its day for readers west of UTC (a timestamp is an instant and still takes Twig's zone; a string without an offset
 * is read in Twig's zone).
 *
 * Extra attributes of `nowo_time_tag`: names are compared lower-case; event handlers (`on*`), `style` and URL
 * attributes (`href`, `src`, `action`, `formaction`, `xlink:href`) are refused, as are the tag's own attributes
 * (`datetime`, `title`, `data-no-relative`) and duplicates; values must be scalar or null.
 *
 * Stateless (the formatter is the host's service), safe in workers.
 */
final class DateExtension extends AbstractExtension
{
    /** Valid HTML attribute name of an extra attribute of `nowo_time_tag`. */
    private const ATTRIBUTE_NAME = '/^[a-zA-Z_:][a-zA-Z0-9_:.\-]*$/';

    /** Attributes a caller may never pass: script, style and URL carriers (`on*` is refused by prefix). */
    private const REFUSED_ATTRIBUTES = ['style', 'href', 'src', 'action', 'formaction', 'xlink:href'];

    /** The tag's own attributes. */
    private const OWN_ATTRIBUTES = ['datetime', 'title', 'data-no-relative'];

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
        $date = $this->dateOf($env, $date, $kind);

        return $date instanceof DateTimeImmutable ? $this->formatter->format($date, $kind, $zone) : '';
    }

    /**
     * `<time datetime="ISO-8601" title="…">text</time>`; '' for null or ''.
     *
     * @param string               $kind       one of {@see DateFormatterInterface::KINDS}
     * @param bool                 $zone       append the zone's abbreviation to the text
     * @param array<string, mixed> $attributes extra attributes (`class`, `data-testid`…): scalar values, null skips one;
     *                                         refused (InvalidArgumentException): `on*`, `style`, URL attributes, the
     *                                         tag's own attributes, duplicates, non-scalar values
     */
    public function timeTag(Environment $env, mixed $date, string $kind = DateFormatterInterface::DATETIME, bool $zone = false, array $attributes = []): string
    {
        $extra = $this->attributes($attributes);
        $date = $this->dateOf($env, $date, $kind);
        if (!$date instanceof DateTimeImmutable) {
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
        foreach ($own + $extra as $name => $value) {
            $html .= ' '.$name.'="'.$this->escape($value).'"';
        }

        return $html.'>'.$this->escape($text).'</time>';
    }

    /**
     * The extra attributes of `nowo_time_tag`, lower-case names and string values, null ones left out.
     *
     * @param array<mixed> $attributes
     *
     * @return array<string, string>
     */
    private function attributes(array $attributes): array
    {
        $out = [];
        $seen = [];
        foreach ($attributes as $name => $value) {
            $lower = strtolower((string) $name);
            if (1 !== preg_match(self::ATTRIBUTE_NAME, $lower) || str_starts_with($lower, 'on') || \in_array($lower, self::REFUSED_ATTRIBUTES, true)) {
                throw new InvalidArgumentException(\sprintf('nowo_time_tag: attribute "%s" is not allowed.', $name));
            }
            if (\in_array($lower, self::OWN_ATTRIBUTES, true) || isset($seen[$lower])) {
                throw new InvalidArgumentException(\sprintf('nowo_time_tag: attribute "%s" is the tag\'s own or given twice.', $name));
            }
            $seen[$lower] = true;
            if (null === $value) {
                continue;
            }
            if (!\is_scalar($value)) {
                throw new InvalidArgumentException(\sprintf('nowo_time_tag: attribute "%s" must be a scalar or null, got %s.', $name, get_debug_type($value)));
            }
            $out[$lower] = \is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        return $out;
    }

    /**
     * Like Twig's `|date`: a date, a timestamp (int, float or digit string) or a date string, as an immutable copy (the
     * caller's object is never changed); null and '' are no date. Kinds with a time take Twig's time zone; the day kinds
     * keep the zone of a `DateTimeInterface` (a calendar day is not moved).
     */
    private function dateOf(Environment $env, mixed $date, string $kind): ?DateTimeImmutable
    {
        if (null === $date || '' === $date) {
            return null;
        }
        $zone = $env->getExtension(CoreExtension::class)->getTimezone();
        if ($date instanceof DateTimeInterface) {
            $copy = DateTimeImmutable::createFromInterface($date);

            return \in_array($kind, [DateFormatterInterface::DATE, DateFormatterInterface::MONTH], true) ? $copy : $copy->setTimezone($zone);
        }
        if (\is_int($date) || \is_float($date) || (\is_string($date) && 1 === preg_match('/^-?\d+$/', $date))) {
            return (new DateTimeImmutable('@'.(\is_float($date) ? (int) $date : $date)))->setTimezone($zone);
        }
        if (!\is_string($date)) {
            throw new InvalidArgumentException(\sprintf('nowo_date / nowo_time_tag: expected a date, a timestamp or a date string, got %s.', get_debug_type($date)));
        }
        $parsed = new DateTimeImmutable($date, $zone);

        return \in_array($kind, [DateFormatterInterface::DATE, DateFormatterInterface::MONTH], true) ? $parsed : $parsed->setTimezone($zone);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
