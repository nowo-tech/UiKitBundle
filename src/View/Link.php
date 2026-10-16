<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Plain navigation link (auth footer links, KPI / card links).
 */
final readonly class Link
{
    /**
     * @param array<string, string|int|float|bool|null> $attributes extra HTML attributes (no on* handlers)
     */
    public function __construct(
        public Label|string $label,
        public string $href,
        public array $attributes = [],
    ) {
        Action::assertAttributes($attributes);
    }
}
