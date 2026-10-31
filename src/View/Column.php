<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Table column of a {@see ListPage}. The default `nowo_ui_list_row` block prints `row[key]` / `row.key`;
 * bundles override the block for rich cells.
 *
 * `priority` (responsive tables, `ListPage::$responsive = 'cards'`): null / 1 = always shown, 2 = hidden when the
 * table container is narrower than 48rem, 3 = hidden below 60rem; `required` columns never hide. In card mode
 * (container below 40rem) every column shows again, labelled with `label`.
 *
 * Sorting (2.20): `sortable` (or a `sortKey`) makes the header a sort control. `sortKey` is the whitelisted key the
 * header sends (default the column `key`; {@see \Nowo\UiKitBundle\Listing\SortRequest::fieldsOf()} builds the
 * whitelist), `sortFirst` the direction of the first click (`desc` for dates and counts). Client-side tables
 * (`Table::$clientSort`): `sortType` (`number` | `date` | `text`, default automatic) and `sortValueKey` (the row key /
 * property whose value the default cells print as `data-sort-value`, e.g. a timestamp behind a formatted date).
 */
final readonly class Column
{
    public const ALIGNS = ['start', 'center', 'end'];

    public const SORT_TYPES = ['number', 'date', 'text'];

    public function __construct(
        public string $key,
        public Label|string|null $label = null,
        public bool $sortable = false,
        public string $align = 'start',
        public ?string $class = null,
        public bool $required = false,
        public ?int $priority = null,
        public ?string $sortKey = null,
        public string $sortFirst = 'asc',
        public ?string $sortType = null,
        public ?string $sortValueKey = null,
    ) {
        if (null !== $sortKey && '' === trim($sortKey)) {
            throw new \InvalidArgumentException(\sprintf('The sortKey of the Column "%s" must be non-empty (null: the column key).', $key));
        }
        if (!\in_array($sortFirst, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Column sortFirst "%s" (asc|desc).', $sortFirst));
        }
        if (null !== $sortType && !\in_array($sortType, self::SORT_TYPES, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Column sortType "%s" (%s).', $sortType, implode('|', self::SORT_TYPES)));
        }
        if (null !== $priority && ($priority < 1 || $priority > 3)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Column priority %d (1 = always shown, 2 = hidden below 48rem, 3 = hidden below 60rem of table width).', $priority));
        }
        if ('' === trim($key)) {
            throw new \InvalidArgumentException('A Column needs a non-empty key.');
        }
        if (!\in_array($align, self::ALIGNS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Column align "%s" (%s).', $align, implode('|', self::ALIGNS)));
        }
    }

    /** Whether the header is a sort control (`sortable`, or a `sortKey` given). */
    public function isSortable(): bool
    {
        return $this->sortable || null !== $this->sortKey;
    }

    /** Key the header sorts by: `sortKey`, else the column key. */
    public function sortField(): string
    {
        return $this->sortKey ?? $this->key;
    }
}
