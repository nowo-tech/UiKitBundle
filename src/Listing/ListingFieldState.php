<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

/**
 * A listing field placed in a resolved {@see ListingLayout}, with its visibility.
 *
 * `merged` (columns only): render this column inside the cell of the previous visible column, below its content
 * ("stack under previous").
 */
final readonly class ListingFieldState
{
    public function __construct(
        public ListingField $field,
        public bool $visible,
        public bool $merged = false,
    ) {
    }
}
