<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * One breadcrumb of a {@see PageHeader} (plain trail when BreadcrumbKit is not used). No href = current page.
 */
final readonly class Crumb
{
    public function __construct(
        public Label|string $label,
        public ?string $href = null,
    ) {
    }
}
