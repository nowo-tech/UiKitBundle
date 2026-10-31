<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use Nowo\UiKitBundle\Listing\SortRequest;

/**
 * A data table (2.2) rendered by the `table` theme partial (`partials/_table.html.twig`): columns, rows, row actions
 * (with their confirm dialogs), caption, sort links and the responsive card mode. The list scaffold renders its table
 * through it ({@see ListPage::$table}); other scaffolds and bundle pages include it (e.g. from `nowo_ui_show_after`)
 * instead of copying the table markup:
 *
 *     {{ include(nowo_ui_partial('table', page.bundle), {table: table}, false) }}
 *
 * Rich cells: `{% embed %}` the partial and override `nowo_ui_table_row` (`row`, `row_key`, `table`, `loop` in
 * context) / `nowo_ui_table_head`.
 * `rowActions`: a callable `fn (mixed $row, int|string $key): iterable<Action>` or one static list for every row.
 * `sort` / `direction`: the sorted column and its direction (sortable columns link to `sort` / `direction` query
 * parameters of the current route). 2.20: `sorting` (a {@see SortRequest}) replaces them — its field / direction,
 * query parameter names (default `sort` / `dir`) and first-click directions; header links keep the current query and
 * drop its page parameter. `clientSort` (2.20): the table is rendered whole and sorts in the browser (Stimulus peer
 * `table-sort`, `stimulus-peers/table_sort_controller.ts`): sortable headers are buttons, no request — never on a
 * paginated table. `actionsLabel` / `actionsLabelVisible` / `actionsAlign` / `actionsPosition`:
 * the row-actions column (header visible by default, `end` aligned, last). `responsive`: `cards` | `scroll`.
 * Accessible name: the caption when there is one (visible or visually hidden), else `label` as `aria-label` (the list
 * scaffold passes the page title).
 */
final readonly class Table
{
    public const RESPONSIVE = ['cards', 'scroll'];

    public const ACTIONS_POSITIONS = ['start', 'end'];

    /** @var list<Column> */
    public array $columns;

    /** @var array<int|string, mixed> */
    public array $rows;

    /** Sorted column key (`SortRequest::$field`), null when the rows keep their own order. */
    public ?string $sort;

    /** `asc` | `desc`. */
    public string $direction;

    /** Sort state of the headers (2.20): the given {@see SortRequest} completed with the columns' first-click directions. */
    public SortRequest $sorting;

    /** @var list<Action> */
    private array $staticRowActions;

    private ?\Closure $rowActionsFactory;

    /**
     * @param iterable<Column>                                                 $columns
     * @param iterable<mixed>                                                  $rows
     * @param (callable(mixed, int|string): iterable<Action>)|iterable<Action> $rowActions
     * @param array<string, string|int|float|bool|null>                        $attributes extra attributes of the <table> (no on* handlers)
     */
    public function __construct(
        iterable $columns = [],
        iterable $rows = [],
        callable|iterable $rowActions = [],
        public Label|string|null $caption = null,
        public bool $captionVisible = true,
        ?string $sort = null,
        string $direction = 'asc',
        public Label|string|null $actionsLabel = null,
        public bool $actionsLabelVisible = true,
        public string $actionsAlign = 'end',
        public string $actionsPosition = 'end',
        public string $responsive = 'cards',
        public ?string $bundle = null,
        public array $attributes = [],
        public Label|string|null $label = null,
        ?SortRequest $sorting = null,
        public bool $clientSort = false,
    ) {
        if (!\in_array($actionsAlign, Column::ALIGNS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Table actionsAlign "%s" (%s).', $actionsAlign, implode('|', Column::ALIGNS)));
        }
        if (!\in_array($actionsPosition, self::ACTIONS_POSITIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Table actionsPosition "%s" (%s).', $actionsPosition, implode('|', self::ACTIONS_POSITIONS)));
        }
        if (!\in_array($responsive, self::RESPONSIVE, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Table responsive "%s" (%s).', $responsive, implode('|', self::RESPONSIVE)));
        }
        if (!\in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Table direction "%s" (asc|desc).', $direction));
        }
        Action::assertAttributes($attributes);
        $this->columns = ViewList::of($columns, Column::class, 'Table columns');
        $sorting ??= new SortRequest($sort, $direction, [], 'sort', 'direction');
        $this->sorting = new SortRequest($sorting->field, $sorting->direction, array_merge(SortRequest::fieldsOf($this->columns), $sorting->fields), $sorting->sortParam, $sorting->directionParam, $sorting->pageParam);
        $this->sort = $this->sorting->field;
        $this->direction = $this->sorting->direction;
        $this->rows = \is_array($rows) ? $rows : iterator_to_array($rows, false);
        if (\is_callable($rowActions)) {
            $this->rowActionsFactory = $rowActions(...);
            $this->staticRowActions = [];
        } else {
            $this->rowActionsFactory = null;
            $this->staticRowActions = ViewList::of($rowActions, Action::class, 'Table rowActions');
        }
    }

    public function hasRowActions(): bool
    {
        return null !== $this->rowActionsFactory || [] !== $this->staticRowActions;
    }

    /**
     * Actions of one row. Actions with a {@see Confirm} and no {@see Action::$confirmScope} are scoped by the row key,
     * so their derived confirm dialog id is unique per row.
     *
     * @return list<Action>
     */
    public function rowActions(mixed $row, int|string $key = 0): array
    {
        if (null === $this->rowActionsFactory) {
            $actions = $this->staticRowActions;
        } else {
            $actions = ($this->rowActionsFactory)($row, $key);
            $actions = ViewList::of(is_iterable($actions) ? $actions : [], Action::class, 'Table rowActions()');
        }

        return array_map(static fn (Action $action): Action => $action->confirm instanceof Confirm && null === $action->confirmScope ? $action->withConfirmScope($key) : $action, $actions);
    }

    /**
     * Confirm dialogs to render after the table: one per {@see Action::confirmId()} (an identical action + Confirm
     * repeated — same id and {@see Action::confirmKey()} — renders once). Two actions with the same explicit
     * {@see Confirm::$id} but different content would print duplicate DOM ids: that is rejected.
     *
     * @param iterable<Action> $actions
     *
     * @return list<Action>
     */
    public function confirmDialogs(iterable $actions): array
    {
        $dialogs = [];
        foreach ($actions as $action) {
            $id = $action->confirmId();
            if (null === $id) {
                continue;
            }
            if (isset($dialogs[$id])) {
                if ($dialogs[$id]->confirmKey() !== $action->confirmKey()) {
                    throw new \LogicException(\sprintf('Confirm id "%s" is used by row actions with different content (target, token or confirm text): give each Confirm its own id, or omit it to derive one per row.', $id));
                }
                continue;
            }
            $dialogs[$id] = $action;
        }

        return array_values($dialogs);
    }

    /**
     * Direction a sortable column's header sorts by on click: the sorted column toggles, another starts with its
     * first-click direction (`Column::$sortFirst`, or the {@see SortRequest} whitelist).
     */
    public function nextDirection(Column|string $column): string
    {
        return $this->sorting->nextDirection($column instanceof Column ? $column->sortField() : $column);
    }

    /** Whether the rows are sorted by this column. */
    public function isSortedBy(Column $column): bool
    {
        return $this->sorting->isActive($column->sortField());
    }

    /** `aria-sort` of the column header: `ascending` | `descending` when the rows are sorted by it, else null. */
    public function ariaSort(Column $column): ?string
    {
        if (!$this->isSortedBy($column)) {
            return null;
        }

        return SortRequest::ASC === $this->direction ? 'ascending' : 'descending';
    }

    /**
     * Query of a server-side header link (2.20): `$query` (the current route parameters + request query) with the
     * next sort of `$column`, without the page parameter.
     *
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    public function sortQuery(Column $column, array $query = []): array
    {
        return $this->sorting->linkQuery($column->sortField(), $query);
    }
}
