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
 * `actionsLabel` / `actionsLabelVisible`: header of the row-actions column (default `table.actions`; visible by
 * default since 2.2, `actionsLabelVisible: false` keeps it for assistive tech only); `actionsPosition` (2.2,
 * `start` | `end`, default `end`): the row-actions column first or last;
 * `actionsAlign` (`start` | `center` | `end`, default `end`): alignment of that header and its cells (slots
 * `table_actions_head` / `table_actions_cell`).
 * Filters: `filterFields` (per-field placeholder / width / classes), `filtersSubmit` (label / icon / variant of the
 * apply button), `filtersClear` (the clear link as an Action; default: a plain link to `filtersClearHref`),
 * `filterActions` (extra actions), `filtersDisplay` (`icon` | `text` | `icon_text`: display of the apply / clear /
 * extra filter buttons whose Action has no `display`), `headerActionsInFilters` (true: the page-header actions render
 * at the end of the filter row instead of the page header, when there are filters; null = the theme option
 * `theme.pages.list.header_actions_in_filters`, default false). `responsive`: `cards` (default; the table turns into labelled cards on narrow
 * containers and hides `Column::priority` columns first) or `scroll` (horizontal scroll only).
 * `caption` / `captionVisible` (2.2): the table `<caption>` (slot `table_caption`), visually hidden when false.
 */
final readonly class ListPage
{
    /** Responsive behaviour of the table: `cards` (card mode on narrow containers), `scroll` (horizontal scroll only). */
    public const RESPONSIVE = ['cards', 'scroll'];

    /** Position of the row-actions column (2.2). */
    public const ACTIONS_POSITIONS = ['start', 'end'];

    /** @var list<Column> */
    public array $columns;

    /** @var array<int|string, mixed> */
    public array $rows;

    /** The table of the page (2.2): rendered by the `table` partial; built from the table arguments below. */
    public Table $table;

    /** @var array<string, FilterField> filter field options keyed by form child name */
    public array $filterFields;

    /** @var list<Action> */
    public array $filterActions;

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
        public bool $actionsLabelVisible = true,
        iterable $filterFields = [],
        public ?Action $filtersSubmit = null,
        public ?Action $filtersClear = null,
        iterable $filterActions = [],
        public string $responsive = 'cards',
        public ?string $filtersDisplay = null,
        public ?bool $headerActionsInFilters = null,
        public string $actionsAlign = 'end',
        public string $actionsPosition = 'end',
        public bool $captionVisible = true,
    ) {
        if (!\in_array($actionsPosition, self::ACTIONS_POSITIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid ListPage actionsPosition "%s" (%s).', $actionsPosition, implode('|', self::ACTIONS_POSITIONS)));
        }
        if (!\in_array($actionsAlign, Column::ALIGNS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid ListPage actionsAlign "%s" (%s).', $actionsAlign, implode('|', Column::ALIGNS)));
        }
        Action::assertAttributes($attributes);
        if (null !== $filtersDisplay && !\in_array($filtersDisplay, Action::DISPLAYS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid ListPage filtersDisplay "%s" (%s).', $filtersDisplay, implode('|', Action::DISPLAYS)));
        }
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
        if (!\is_callable($rowActions)) {
            $rowActions = ViewList::of($rowActions, Action::class, 'ListPage rowActions');
        }
        if (!\in_array($direction, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid ListPage direction "%s" (asc|desc).', $direction));
        }
        $this->table = new Table($this->columns, $this->rows, $rowActions, $caption, $captionVisible, $sort, $direction, $actionsLabel, $actionsLabelVisible, $actionsAlign, $actionsPosition, $responsive, $bundle);
    }

    public function hasRowActions(): bool
    {
        return $this->table->hasRowActions();
    }

    /**
     * Actions of one row ({@see Table::rowActions()}: confirm actions are scoped by the row key).
     *
     * @return list<Action>
     */
    public function rowActions(mixed $row, int|string $key = 0): array
    {
        return $this->table->rowActions($row, $key);
    }

    /**
     * Confirm dialogs to render after the table ({@see Table::confirmDialogs()}).
     *
     * @param iterable<Action> $actions
     *
     * @return list<Action>
     */
    public function confirmDialogs(iterable $actions): array
    {
        return $this->table->confirmDialogs($actions);
    }

    /**
     * Direction a sortable column's link toggles to.
     */
    public function nextDirection(string $column): string
    {
        return $this->table->nextDirection($column);
    }
}
