<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use InvalidArgumentException;

/**
 * One key figure of a stat row (2.21, `stat_row` partial, {@see ShowPage::$stats}): a compact tile with a label over
 * its value — uptime, response time, open incidents… Lighter than a {@see KpiTile} (no card, smaller type) so a row
 * of them fits above the details of a record. `tone` colours the value (slot `stat_<tone>` modifier), `href` makes
 * the value a link, `hint` adds a muted line under it. Values are printed escaped.
 */
final readonly class Stat
{
    public const TONES = ['neutral', 'success', 'warning', 'danger', 'info'];

    public function __construct(
        public Label|string $label,
        public Label|string|int|float $value,
        public Label|string|null $hint = null,
        public string $tone = 'neutral',
        public ?string $href = null,
    ) {
        if (!\in_array($tone, self::TONES, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid Stat tone "%s" (%s).', $tone, implode('|', self::TONES)));
        }
    }
}
