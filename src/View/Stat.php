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
 */
final readonly class Stat
{
    public const TONES = ['neutral', 'success', 'warning', 'danger', 'info'];

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
    ) {
        Action::assertAttributes($attributes);
        Action::assertAttributes($valueAttributes);
        if (!\in_array($tone, self::TONES, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid Stat tone "%s" (%s).', $tone, implode('|', self::TONES)));
        }
    }
}
