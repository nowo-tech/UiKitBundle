<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

use Nowo\UiKitBundle\View\Column;
use Symfony\Component\HttpFoundation\Request;

/**
 * Validated sort of a server-side list (2.20): the query parameters `sort` / `dir` checked against a whitelist.
 *
 * `fields` is the whitelist: sort key => direction of the first click on its header (`asc` | `desc`; a plain list of
 * keys means `asc` for all). An unknown or missing key falls back to the default sort (`defaultField`, or no sort:
 * the list's own order) — never an error — and an unknown direction to the key's first-click direction. Hosts read it
 * in the controller, order their query by {@see self::$field} / {@see self::$direction} (always with a stable
 * tie-breaker) and pass it to the view ({@see \Nowo\UiKitBundle\View\ListPage::$sorting} /
 * {@see \Nowo\UiKitBundle\View\Table::$sorting}), whose sortable headers link to the next state:
 *
 *     $sort = SortRequest::fromRequest($request, ['name' => 'asc', 'createdAt' => 'desc'], 'createdAt');
 *     $rows = $repository->search($filters, $sort->field, $sort->direction);
 *     return $this->render('@NowoUiKit/page/list.html.twig', ['page' => new ListPage($header, $columns, $rows, sorting: $sort)]);
 *
 * Header links keep the current query (filters, rows per page), set `sortParam` / `directionParam` and drop
 * `pageParam` (a new order starts on page 1).
 */
final readonly class SortRequest
{
    public const ASC = 'asc';

    public const DESC = 'desc';

    public const DIRECTIONS = [self::ASC, self::DESC];

    /** @var array<string, string> sort key => first-click direction */
    public array $fields;

    /**
     * @param string|null                        $field     active sort key (null: the list's own order, no header marked)
     * @param array<string, string>|list<string> $fields    whitelist: sort key => first-click direction, or a list of keys (asc)
     * @param string|null                        $pageParam query parameter dropped from header links (null keeps it)
     */
    public function __construct(
        public ?string $field = null,
        public string $direction = self::ASC,
        array $fields = [],
        public string $sortParam = 'sort',
        public string $directionParam = 'dir',
        public ?string $pageParam = 'page',
    ) {
        if (!\in_array($direction, self::DIRECTIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid sort direction "%s" (asc|desc).', $direction));
        }
        if ('' === trim($sortParam) || '' === trim($directionParam) || $sortParam === $directionParam) {
            throw new \InvalidArgumentException('A SortRequest needs two distinct, non-empty query parameter names.');
        }
        $this->fields = self::normalizeFields($fields);
    }

    /**
     * Reads `sortParam` / `directionParam` from the request query.
     *
     * @param array<string, string>|list<string> $fields whitelist (see the class doc)
     */
    public static function fromRequest(Request $request, array $fields, ?string $defaultField = null, ?string $defaultDirection = null, string $sortParam = 'sort', string $directionParam = 'dir', ?string $pageParam = 'page'): self
    {
        return self::fromQuery($request->query->all()[$sortParam] ?? null, $request->query->all()[$directionParam] ?? null, $fields, $defaultField, $defaultDirection, $sortParam, $directionParam, $pageParam);
    }

    /**
     * Validates raw query values (anything a query string can carry: strings, arrays, null).
     *
     * @param array<string, string>|list<string> $fields whitelist (see the class doc)
     */
    public static function fromQuery(mixed $sort, mixed $direction, array $fields, ?string $defaultField = null, ?string $defaultDirection = null, string $sortParam = 'sort', string $directionParam = 'dir', ?string $pageParam = 'page'): self
    {
        $fields = self::normalizeFields($fields);
        if (null !== $defaultField && !isset($fields[$defaultField])) {
            throw new \InvalidArgumentException(\sprintf('The default sort "%s" is not one of the sortable keys (%s).', $defaultField, implode('|', array_keys($fields))));
        }
        if (null !== $defaultDirection && !\in_array($defaultDirection, self::DIRECTIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid sort direction "%s" (asc|desc).', $defaultDirection));
        }
        if (!\is_string($sort) || !isset($fields[$sort])) {
            $direction = $defaultDirection ?? (null === $defaultField ? self::ASC : $fields[$defaultField]);

            return new self($defaultField, $direction, $fields, $sortParam, $directionParam, $pageParam);
        }
        $direction = \is_string($direction) ? strtolower($direction) : '';

        return new self($sort, \in_array($direction, self::DIRECTIONS, true) ? $direction : $fields[$sort], $fields, $sortParam, $directionParam, $pageParam);
    }

    /**
     * The whitelist declared by sortable columns ({@see Column::sortField()} => {@see Column::$sortFirst}, or
     * {@see ListingField::$sortKey} => {@see ListingField::$sortFirst}).
     *
     * @param iterable<Column|ListingField>|ListingDefinitionInterface $columns
     *
     * @return array<string, string>
     */
    public static function fieldsOf(iterable|ListingDefinitionInterface $columns): array
    {
        if ($columns instanceof ListingDefinitionInterface) {
            $columns = $columns->columns();
        }
        $fields = [];
        foreach ($columns as $column) {
            if ($column instanceof Column && $column->isSortable()) {
                $fields[$column->sortField()] = $column->sortFirst;
            } elseif ($column instanceof ListingField && null !== $column->sortKey) {
                $fields[$column->sortKey] = $column->sortFirst;
            }
        }

        return $fields;
    }

    public function isActive(string $field): bool
    {
        return null !== $this->field && $field === $this->field;
    }

    /** Direction a click on the header of `$field` sorts by: the active one toggles, another starts with its first-click direction. */
    public function nextDirection(string $field): string
    {
        if ($this->isActive($field)) {
            return self::ASC === $this->direction ? self::DESC : self::ASC;
        }

        return $this->fields[$field] ?? self::ASC;
    }

    /**
     * Query parameters that keep this sort (pagination links, filter forms' hidden fields); empty without a sort.
     *
     * @return array<string, string>
     */
    public function query(): array
    {
        return null === $this->field ? [] : [$this->sortParam => $this->field, $this->directionParam => $this->direction];
    }

    /**
     * Query of the header link of `$field`: `$current` (the request query) with the next sort, without the page.
     *
     * @param array<string, mixed> $current
     *
     * @return array<string, mixed>
     */
    public function linkQuery(string $field, array $current = []): array
    {
        if (null !== $this->pageParam) {
            unset($current[$this->pageParam]);
        }

        return array_merge($current, [$this->sortParam => $field, $this->directionParam => $this->nextDirection($field)]);
    }

    /** Same sort with another page parameter (the list's {@see \Nowo\UiKitBundle\View\Pagination::$pageParam}). */
    public function withPageParam(?string $pageParam): self
    {
        return $pageParam === $this->pageParam ? $this : new self($this->field, $this->direction, $this->fields, $this->sortParam, $this->directionParam, $pageParam);
    }

    /**
     * Sorts rows built in PHP (arrays or objects, before paging) by the value `$extractors[field]` returns (a callable,
     * or a key / property name); without an extractor the row key / property named like the field. Empty values
     * (null, '') last, equal values keep their order; strings compare naturally and case-insensitively.
     *
     * @template T
     *
     * @param iterable<array-key, T>                   $rows
     * @param array<string, string|callable(T): mixed> $extractors
     *
     * @return list<T>
     */
    public function sortRows(iterable $rows, array $extractors = []): array
    {
        $rows = \is_array($rows) ? array_values($rows) : iterator_to_array($rows, false);
        if (null === $this->field) {
            return $rows;
        }
        $extract = $extractors[$this->field] ?? $this->field;
        $value = \is_callable($extract) && !\is_string($extract) ? $extract : static fn (mixed $row): mixed => self::read($row, (string) $extract);

        $keyed = [];
        foreach ($rows as $index => $row) {
            $keyed[] = [$value($row), $index, $row];
        }
        $sign = self::DESC === $this->direction ? -1 : 1;
        usort($keyed, static function (array $a, array $b) use ($sign): int {
            $aEmpty = null === $a[0] || '' === $a[0];
            $bEmpty = null === $b[0] || '' === $b[0];
            if ($aEmpty || $bEmpty) {
                return $aEmpty === $bEmpty ? $a[1] <=> $b[1] : ($aEmpty ? 1 : -1);
            }
            $order = \is_string($a[0]) && \is_string($b[0]) ? strnatcasecmp($a[0], $b[0]) : $a[0] <=> $b[0];

            return 0 !== $order ? $sign * $order : $a[1] <=> $b[1];
        });

        return array_map(static fn (array $entry): mixed => $entry[2], $keyed);
    }

    private static function read(mixed $row, string $key): mixed
    {
        if (\is_array($row) || $row instanceof \ArrayAccess) {
            return $row[$key] ?? null;
        }
        if (\is_object($row)) {
            return $row->{$key} ?? null;
        }

        return null;
    }

    /**
     * @param array<array-key, string> $fields
     *
     * @return array<string, string>
     */
    private static function normalizeFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $key => $value) {
            [$field, $first] = \is_int($key) ? [$value, self::ASC] : [$key, $value];
            if ('' === trim($field)) {
                throw new \InvalidArgumentException('Sort keys must be non-empty strings.');
            }
            if (!\in_array($first, self::DIRECTIONS, true)) {
                throw new \InvalidArgumentException(\sprintf('Invalid first-click direction "%s" of the sort key "%s" (asc|desc).', $first, $field));
            }
            $normalized[$field] = $first;
        }

        return $normalized;
    }
}
