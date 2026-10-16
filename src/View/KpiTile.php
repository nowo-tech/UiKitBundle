<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * KPI tile of a {@see DashboardPage}.
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
    ) {
        if (!\in_array($variant, self::VARIANTS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid KpiTile variant "%s" (%s).', $variant, implode('|', self::VARIANTS)));
        }
    }
}
