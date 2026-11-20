<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use InvalidArgumentException;

/**
 * One key figure of a stat row (2.21, `stat_row` partial, {@see ShowPage::$stats}): a compact tile with a label over
 * its value — uptime, response time, open incidents… Lighter than a {@see KpiTile} (no card, smaller type) so a row
 * of them fits above the details of a record. `tone` colours the value (slot `stat_<tone>` modifier), `href` makes
 * the value a link, `hint` adds a muted line under it. Values are printed escaped.
 *
 * 2.23: `attributes` adds host attributes to the tile (`<div>`) and `valueAttributes` to the value `<dd>` (e.g.
 * `data-uptime-24h` that a live script updates); validated by {@see Action::assertAttributes()} (no `on*`), rendered
 * escaped. Empty (default): unchanged markup.
 *
 * 2.61 (statistic card): `delta` is the change shown under the value ("+12 %", "−3"), `trend` its direction (`up`,
 * `down`, `flat`: an arrow plus a visually hidden word, never colour alone) and `deltaTone` whether that change is
 * good (`positive`), bad (`negative`) or `neutral` (default: up = positive, down = negative, flat = neutral; an error
 * count going up passes `negative`). `icon` is a name for `components/_icon.html.twig` shown in the tile corner
 * (decorative). The host computes every number. All null (default): unchanged markup.
 */
final readonly class Stat
{
    public const TONES = ['neutral', 'success', 'warning', 'danger', 'info'];

    public const TRENDS = ['up', 'down', 'flat'];

    public const DELTA_TONES = ['positive', 'negative', 'neutral'];

    /**
     * @param array<string, string|int|float|bool|null> $attributes      extra attributes of the tile (2.23; no on*)
     * @param array<string, string|int|float|bool|null> $valueAttributes extra attributes of the value `<dd>` (2.23; no on*)
     */
    public function __construct(
        public Label|string $label,
        public Label|string|int|float $value,
        public Label|string|null $hint = null,
        public string $tone = 'neutral',
        public ?string $href = null,
        public array $attributes = [],
        public array $valueAttributes = [],
        public Label|string|null $delta = null,
        public ?string $trend = null,
        public ?string $deltaTone = null,
        public ?string $icon = null,
    ) {
        Action::assertAttributes($attributes);
        Action::assertAttributes($valueAttributes);
        if (!\in_array($tone, self::TONES, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid Stat tone "%s" (%s).', $tone, implode('|', self::TONES)));
        }
        if (null !== $trend && !\in_array($trend, self::TRENDS, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid Stat trend "%s" (%s).', $trend, implode('|', self::TRENDS)));
        }
        if (null !== $deltaTone && !\in_array($deltaTone, self::DELTA_TONES, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid Stat delta tone "%s" (%s).', $deltaTone, implode('|', self::DELTA_TONES)));
        }
    }

    /**
     * Tone of the delta: the explicit `deltaTone`, else from the trend (up = positive, down = negative, else neutral).
     */
    public function resolvedDeltaTone(): string
    {
        return $this->deltaTone ?? match ($this->trend) {
            'up' => 'positive',
            'down' => 'negative',
            default => 'neutral',
        };
    }
}
