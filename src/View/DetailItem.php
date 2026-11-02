<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use InvalidArgumentException;

/**
 * One term / description pair of a {@see DetailSection} (`<dt>` / `<dd>`). Values are printed escaped.
 * `badge` (2.2): print the value as a badge of that tone (slot `badge_<tone>`: primary, success, warning, danger,
 * info, neutral), e.g. an order status.
 *
 * 2.23: `attributes` adds host attributes to the item wrapper (`<div>` around `<dt>` / `<dd>`, e.g. a cell of the
 * `facts` layout) and `valueAttributes` to the value `<dd>`, for live updates (`data-uptime-24h`…); validated by
 * {@see Action::assertAttributes()} (no `on*`), rendered escaped. Empty (default): unchanged markup.
 */
final readonly class DetailItem
{
    public const BADGES = ['primary', 'success', 'warning', 'danger', 'info', 'neutral'];

    /**
     * @param array<string, string|int|float|bool|null> $attributes      extra attributes of the item `<div>` (2.23; no on*)
     * @param array<string, string|int|float|bool|null> $valueAttributes extra attributes of the value `<dd>` (2.23; no on*)
     */
    public function __construct(
        public Label|string $label,
        public Label|string|int|float|bool|null $value,
        public ?string $href = null,
        public ?string $badge = null,
        public array $attributes = [],
        public array $valueAttributes = [],
    ) {
        Action::assertAttributes($attributes);
        Action::assertAttributes($valueAttributes);
        if (null !== $badge && !\in_array($badge, self::BADGES, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid DetailItem badge "%s" (%s).', $badge, implode('|', self::BADGES)));
        }
    }
}
