<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * The "back" control of a {@see PageHeader} (2.7): a compact link with the `prev` icon above the title, to the list or
 * parent page ("Back to projects"). Form footers keep "Cancel" for abandoning a form.
 */
final readonly class BackLink
{
    public function __construct(
        public string $href,
        public Label|string $label,
        public ?string $testid = null,
    ) {
    }
}
