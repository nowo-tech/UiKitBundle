<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Empty state of a list (UiKit `_empty`). Defaults to UiKit's `empty.default` message.
 */
final readonly class EmptyState
{
    public function __construct(
        public Label|string|null $title = null,
        public Label|string|null $message = null,
        public ?Action $action = null,
    ) {
    }
}
