<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * KPI tile of a {@see DashboardPage}. With an `href`, the value is the link; `linkTile: true` makes the whole tile
 * the link (`<a>` root, no nested link).
 */
final readonly class KpiTile
{
    public const VARIANTS = ['neutral', 'success', 'warning', 'danger', 'info'];

    public function __construct(
        public Label|string $label,
        public string|int|float $value,
        public Label|string|null $hint = null,
        public ?string $href = null,
        public string $variant = 'neutral',
        public bool $linkTile = false,
    ) {
        if (!\in_array($variant, self::VARIANTS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid KpiTile variant "%s" (%s).', $variant, implode('|', self::VARIANTS)));
        }
    }
}
