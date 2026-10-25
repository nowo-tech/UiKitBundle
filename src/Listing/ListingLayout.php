<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

use Symfony\Component\HttpFoundation\Request;

/**
 * Effective columns and filters of a listing for one viewer: the definition merged with the stored preferences
 * ({@see ListingLayoutMerger::merge()}). `stored` tells whether the viewer has customized the listing.
 */
final readonly class ListingLayout
{
    /**
     * @param list<ListingFieldState> $columns
     * @param list<ListingFieldState> $filters
     */
    public function __construct(
        public string $listingKey,
        public array $columns,
        public array $filters,
        public bool $stored = false,
    ) {
    }

    /**
     * Visible columns grouped into table cells: a column marked `merged` joins the cell of the previous visible
     * column (stacked, one line each). The first visible column always starts a cell.
     *
     * @return list<non-empty-list<ListingField>>
     */
    public function visibleColumnGroups(): array
    {
        $groups = [];
        $current = null;
        foreach ($this->columns as $state) {
            if (!$state->visible) {
                continue;
            }
            if ($state->merged && null !== $current) {
                $current[] = $state->field;
                continue;
            }
            if (null !== $current) {
                $groups[] = $current;
            }
            $current = [$state->field];
        }
        if (null !== $current) {
            $groups[] = $current;
        }

        return $groups;
    }

    /**
     * @return list<ListingField>
     */
    public function visibleColumns(): array
    {
        return $this->visible($this->columns);
    }

    /**
     * @return list<string>
     */
    public function visibleColumnKeys(): array
    {
        return $this->keys($this->visible($this->columns));
    }

    /**
     * @return list<ListingField>
     */
    public function visibleFilters(): array
    {
        return $this->visible($this->filters);
    }

    /**
     * @return list<string>
     */
    public function visibleFilterKeys(): array
    {
        return $this->keys($this->visible($this->filters));
    }

    /**
     * @return list<string>
     */
    public function hiddenFilterKeys(): array
    {
        $keys = [];
        foreach ($this->filters as $state) {
            if (!$state->visible) {
                $keys[] = $state->field->key;
            }
        }

        return $keys;
    }

    public function isColumnVisible(string $key): bool
    {
        return \in_array($key, $this->visibleColumnKeys(), true);
    }

    public function isFilterVisible(string $key): bool
    {
        return \in_array($key, $this->visibleFilterKeys(), true);
    }

    public function hasColumn(string $key): bool
    {
        foreach ($this->columns as $state) {
            if ($state->field->key === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hidden filters that carry a value in the query of `$request` (they are ignored, see {@see self::filterRequest()}).
     *
     * @return list<ListingField>
     */
    public function ignoredFilters(Request $request): array
    {
        $fields = [];
        foreach ($this->filters as $state) {
            if (!$state->visible && self::hasValue($request->query->all()[$state->field->key] ?? null)) {
                $fields[] = $state->field;
            }
        }

        return $fields;
    }

    /**
     * Copy of `$request` without the hidden filters in its query, so nothing filters the list invisibly. A sort on a
     * hidden column is dropped too (the listing falls back to its default sort). Returns `$request` itself when
     * nothing changes.
     */
    public function filterRequest(Request $request, string $sortParam = 'sort', string $directionParam = 'dir'): Request
    {
        $original = $request->query->all();
        $query = $original;
        foreach ($this->hiddenFilterKeys() as $key) {
            unset($query[$key]);
        }

        $sort = $query[$sortParam] ?? null;
        if (\is_string($sort) && $this->hasColumn($sort) && !$this->isColumnVisible($sort)) {
            unset($query[$sortParam], $query[$directionParam]);
        }

        if ($query === $original) {
            return $request;
        }

        return $request->duplicate($query);
    }

    /**
     * Copy with the columns `$keys` hidden (columns that make no sense in the current page state).
     *
     * @param list<string> $keys
     */
    public function withoutColumns(array $keys): self
    {
        $columns = array_map(
            static fn (ListingFieldState $state): ListingFieldState => \in_array($state->field->key, $keys, true)
                ? new ListingFieldState($state->field, false, $state->merged)
                : $state,
            $this->columns,
        );

        return new self($this->listingKey, $columns, $this->filters, $this->stored);
    }

    /**
     * @param list<ListingFieldState> $states
     *
     * @return list<ListingField>
     */
    private function visible(array $states): array
    {
        $fields = [];
        foreach ($states as $state) {
            if ($state->visible) {
                $fields[] = $state->field;
            }
        }

        return $fields;
    }

    /**
     * @param list<ListingField> $fields
     *
     * @return list<string>
     */
    private function keys(array $fields): array
    {
        return array_map(static fn (ListingField $field): string => $field->key, $fields);
    }

    private static function hasValue(mixed $value): bool
    {
        if (\is_array($value)) {
            foreach ($value as $item) {
                if (self::hasValue($item)) {
                    return true;
                }
            }

            return false;
        }

        return null !== $value && '' !== $value;
    }
}
