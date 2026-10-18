<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Page header: title, intro, toolbar actions, breadcrumbs (plain trail, or BreadcrumbKit when installed) and an
 * optional area navigation ({@see AreaNav}, rendered under the title through the `tabs` partial).
 * `breadcrumbsMode` (2.2): `auto` (default: BreadcrumbKit's trail when installed and not empty, else
 * {@see self::$breadcrumbs}), `kit` (BreadcrumbKit only) or `own` (always {@see self::$breadcrumbs}).
 */
final readonly class PageHeader
{
    public const BREADCRUMBS_MODES = ['auto', 'kit', 'own'];

    /** @var list<Action> */
    public array $actions;

    /** @var list<Crumb> */
    public array $breadcrumbs;

    /**
     * @param iterable<Action> $actions
     * @param iterable<Crumb>  $breadcrumbs
     * @param string|null      $breadcrumbCollection BreadcrumbKit collection code (overrides the config default)
     * @param AreaNav|null     $tabs                 area navigation (tab bar between the sections of the area)
     */
    public function __construct(
        public Label|string $title,
        public Label|string|null $intro = null,
        iterable $actions = [],
        iterable $breadcrumbs = [],
        public ?string $breadcrumbCollection = null,
        public ?AreaNav $tabs = null,
        public string $breadcrumbsMode = 'auto',
    ) {
        if (!\in_array($breadcrumbsMode, self::BREADCRUMBS_MODES, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid PageHeader breadcrumbsMode "%s" (%s).', $breadcrumbsMode, implode('|', self::BREADCRUMBS_MODES)));
        }
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
        return new self($this->title, $this->intro, $actions, $this->breadcrumbs, $this->breadcrumbCollection, $this->tabs, $this->breadcrumbsMode);
    }

    /**
     * Copy with another area navigation (e.g. from {@see \Nowo\UiKitBundle\Navigation\AreaNavRegistry}).
     *
     * @return self Fluent interface
     */
    public function withTabs(?AreaNav $tabs): self
    {
        return new self($this->title, $this->intro, $this->actions, $this->breadcrumbs, $this->breadcrumbCollection, $tabs, $this->breadcrumbsMode);
    }
}
