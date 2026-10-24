<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

use Nowo\UiKitBundle\View\Label;

/**
 * One customizable column or filter of a listing, as declared by its {@see ListingDefinitionInterface}.
 *
 * - `key`: column key (`cell_<key>` block of the cells template, sort field) or filter form field name
 * - `label`: a {@see Label} (translated) or a plain string (printed as is)
 * - `required`: always visible (the customizer shows it checked and disabled; the responsive table never hides it)
 * - `defaultVisible`: visibility before the viewer customizes the listing
 * - `priority`, `headClass`, `cellClass` (columns only): `data-priority` of the cells (host CSS), extra `<th>` /
 *   `<td>` classes
 */
final readonly class ListingField
{
    public function __construct(
        public string $key,
        public Label|string $label,
        public bool $required = false,
        public bool $defaultVisible = true,
        public int $priority = 0,
        public string $headClass = '',
        public string $cellClass = '',
    ) {
        if ('' === trim($key)) {
            throw new \InvalidArgumentException('A ListingField needs a non-empty key.');
        }
    }

    public function isVisibleByDefault(): bool
    {
        return $this->required || $this->defaultVisible;
    }
}
