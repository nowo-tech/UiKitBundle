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
 * - `queryKeys` (filters only, 2.15): the query parameters the filter submits when they are not just its key, e.g. a
 *   date range `collection_date` sending `collection_date_from` / `collection_date_to`; a hidden filter drops them
 *   all from the request ({@see ListingLayout::filterRequest()})
 * - `sortKey` / `sortFirst` (columns only, 2.20): the whitelisted sort key of the column header (null = not sortable)
 *   and the direction of its first click; `partials/_listing_head.html.twig` renders the sort link / button
 *   ({@see SortRequest::fieldsOf()} builds the whitelist)
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
        /** @var list<string> */
        public array $queryKeys = [],
        public ?string $sortKey = null,
        public string $sortFirst = 'asc',
    ) {
        if (null !== $sortKey && '' === trim($sortKey)) {
            throw new \InvalidArgumentException(\sprintf('The sortKey of the ListingField "%s" must be non-empty (null: not sortable).', $key));
        }
        if (!\in_array($sortFirst, SortRequest::DIRECTIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid ListingField sortFirst "%s" (asc|desc).', $sortFirst));
        }
        if ('' === trim($key)) {
            throw new \InvalidArgumentException('A ListingField needs a non-empty key.');
        }
        foreach ($queryKeys as $queryKey) {
            if ('' === trim($queryKey)) {
                throw new \InvalidArgumentException(\sprintf('The query keys of the ListingField "%s" must be non-empty strings.', $key));
            }
        }
    }

    /**
     * Query parameters of the filter: its `queryKeys`, else its key.
     *
     * @return list<string>
     */
    public function queryKeys(): array
    {
        return [] === $this->queryKeys ? [$this->key] : $this->queryKeys;
    }

    public function isVisibleByDefault(): bool
    {
        return $this->required || $this->defaultVisible;
    }
}
