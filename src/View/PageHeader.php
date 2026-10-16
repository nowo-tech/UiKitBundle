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
}
