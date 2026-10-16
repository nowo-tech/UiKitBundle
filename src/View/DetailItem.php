<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * One term / description pair of a {@see DetailSection} (`<dt>` / `<dd>`). Values are printed escaped.
 */
final readonly class DetailItem
{
    public function __construct(
        public Label|string $label,
        public Label|string|int|float|bool|null $value,
        public ?string $href = null,
    ) {
    }
}
