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
 */
final readonly class ListPage
{
    /** @var list<Column> */
    public array $columns;

    /** @var array<int|string, mixed> */
    public array $rows;

    /** @var list<Action> */
    private array $staticRowActions;

    private ?\Closure $rowActionsFactory;

    /**
     * @param iterable<Column>                                                 $columns
     * @param iterable<mixed>                                                  $rows
     * @param (callable(mixed, int|string): iterable<Action>)|iterable<Action> $rowActions
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
    ) {
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
     * Actions of one row.
     *
     * @return list<Action>
     */
    public function rowActions(mixed $row, int|string $key = 0): array
    {
        if (null === $this->rowActionsFactory) {
            return $this->staticRowActions;
        }

        $actions = ($this->rowActionsFactory)($row, $key);

        return ViewList::of(is_iterable($actions) ? $actions : [], Action::class, 'ListPage rowActions()');
    }

    /**
     * Direction a sortable column's link toggles to.
     */
    public function nextDirection(string $column): string
    {
        return $this->sort === $column && 'asc' === $this->direction ? 'desc' : 'asc';
    }
}
