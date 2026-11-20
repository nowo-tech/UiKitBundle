<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Empty state of a list (UiKit `_empty`). Defaults to UiKit's `empty.default` message.
 *
 * 2.61: `icon` (a name for `components/_icon.html.twig`, decorative) renders the illustrated layout: the icon in a
 * circle over the title, the message and the action, centred (`nowo-ui-empty--illustrated`). Null (default):
 * unchanged markup.
 */
final readonly class EmptyState
{
    public function __construct(
        public Label|string|null $title = null,
        public Label|string|null $message = null,
        public ?Action $action = null,
        public ?string $icon = null,
    ) {
    }
}
