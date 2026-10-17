<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use Symfony\Component\Form\FormView;

/**
 * View model of `@NowoUiKit/page/list.html.twig`: header + actions, filters (FormView), table frame, row
 * actions, pagination, empty state. Bundles fill `nowo_ui_list_head`, `nowo_ui_list_row` (cells; `row`, `loop`
 * in context) and `nowo_ui_list_after`.
 *
 * `rowActions`: a callable `fn (mixed $row, int|string $key): iterable<Action>` or one static list for every row.
 * `actionsLabel` / `actionsLabelVisible`: header of the row-actions column (default `table.actions`, visually hidden).
 * Filters: `filterFields` (per-field placeholder / width / classes), `filtersSubmit` (label / icon / variant of the
 * apply button), `filtersClear` (the clear link as an Action; default: a plain link to `filtersClearHref`),
 * `filterActions` (extra actions). `responsive`: `cards` (default; the table turns into labelled cards on narrow
 * containers and hides `Column::priority` columns first) or `scroll` (horizontal scroll only).
 */
final readonly class ListPage
{
    /** Responsive behaviour of the table: `cards` (card mode on narrow containers), `scroll` (horizontal scroll only). */
    public const RESPONSIVE = ['cards', 'scroll'];

    /** @var list<Column> */
    public array $columns;

    /** @var array<int|string, mixed> */
    public array $rows;

    /** @var list<Action> */
    private array $staticRowActions;

    /** @var array<string, FilterField> filter field options keyed by form child name */
    public array $filterFields;

    /** @var list<Action> */
    public array $filterActions;

    private ?\Closure $rowActionsFactory;

    /**
     * @param iterable<Column>                                                 $columns
     * @param iterable<mixed>                                                  $rows
     * @param (callable(mixed, int|string): iterable<Action>)|iterable<Action> $rowActions
     * @param iterable<FilterField>                                            $filterFields  per-field filter options (placeholder, width, classes)
     * @param iterable<Action>                                                 $filterActions extra actions after apply / clear
     * @param array<string, string|int|float|bool|null>                        $attributes    extra attributes of the scaffold root (data-testid…; no on* handlers)
     */
    public function __construct(
        public PageHeader $header,
        iterable $columns = [],
        iterable $rows = [],
        public ?FormView $filters = null,
        callable|iterable $rowActions = [],
        public ?Pagination $pagination = null,
        public ?EmptyState $empty = null,
        public ?string $filtersClearHref = null,
        public ?string $sort = null,
        public string $direction = 'asc',
        public Label|string|null $caption = null,
        public ?string $bundle = null,
        public ?string $layout = null,
        public array $attributes = [],
        public Label|string|null $actionsLabel = null,
        public bool $actionsLabelVisible = false,
        iterable $filterFields = [],
        public ?Action $filtersSubmit = null,
        public ?Action $filtersClear = null,
        iterable $filterActions = [],
        public string $responsive = 'cards',
    ) {
        Action::assertAttributes($attributes);
        $fields = [];
        foreach (ViewList::of($filterFields, FilterField::class, 'ListPage filterFields') as $field) {
            $fields[$field->name] = $field;
        }
        $this->filterFields = $fields;
        $this->filterActions = ViewList::of($filterActions, Action::class, 'ListPage filterActions');
        if (!\in_array($responsive, self::RESPONSIVE, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid ListPage responsive "%s" (%s).', $responsive, implode('|', self::RESPONSIVE)));
        }
        $this->columns = ViewList::of($columns, Column::class, 'ListPage columns');
        $this->rows = \is_array($rows) ? $rows : iterator_to_array($rows, false);
        if (\is_callable($rowActions)) {
            $this->rowActionsFactory = $rowActions(...);
            $this->staticRowActions = [];
        } else {
            $this->rowActionsFactory = null;
            $this->staticRowActions = ViewList::of($rowActions, Action::class, 'ListPage rowActions');
        }
        if (!\in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid ListPage direction "%s" (asc|desc).', $direction));
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
            $actions = ViewList::of(is_iterable($actions) ? $actions : [], Action::class, 'ListPage rowActions()');
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
     * Direction a sortable column's link toggles to.
     */
    public function nextDirection(string $column): string
    {
        return $this->sort === $column && 'asc' === $this->direction ? 'desc' : 'asc';
    }
}
