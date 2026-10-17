<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Page header: title, intro, toolbar actions and breadcrumbs (plain trail, or BreadcrumbKit when installed).
 */
final readonly class PageHeader
{
    /** @var list<Action> */
    public array $actions;

    /** @var list<Crumb> */
    public array $breadcrumbs;

    /**
     * @param iterable<Action> $actions
     * @param iterable<Crumb>  $breadcrumbs
     * @param string|null      $breadcrumbCollection BreadcrumbKit collection code (overrides the config default)
     */
    public function __construct(
        public Label|string $title,
        public Label|string|null $intro = null,
        iterable $actions = [],
        iterable $breadcrumbs = [],
        public ?string $breadcrumbCollection = null,
    ) {
        $this->actions = ViewList::of($actions, Action::class, 'PageHeader actions');
        $this->breadcrumbs = ViewList::of($breadcrumbs, Crumb::class, 'PageHeader breadcrumbs');
    }

    /**
     * Copy with other toolbar actions (the list scaffold passes `withActions([])` to the page header when the
     * actions render in the filter row, so overrides reading `header.actions` do not print them twice).
     *
     * @param iterable<Action> $actions
     *
     * @return self Fluent interface
     */
    public function withActions(iterable $actions): self
    {
        return new self($this->title, $this->intro, $actions, $this->breadcrumbs, $this->breadcrumbCollection);
    }
}
