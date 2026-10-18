<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * One term / description pair of a {@see DetailSection} (`<dt>` / `<dd>`). Values are printed escaped.
 * `badge` (2.2): print the value as a badge of that tone (slot `badge_<tone>`: primary, success, warning, danger,
 * info, neutral), e.g. an order status.
 */
final readonly class DetailItem
{
    public const BADGES = ['primary', 'success', 'warning', 'danger', 'info', 'neutral'];

    public function __construct(
        public Label|string $label,
        public Label|string|int|float|bool|null $value,
        public ?string $href = null,
        public ?string $badge = null,
    ) {
        if (null !== $badge && !\in_array($badge, self::BADGES, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid DetailItem badge "%s" (%s).', $badge, implode('|', self::BADGES)));
        }
    }
}
