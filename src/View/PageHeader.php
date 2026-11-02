<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use InvalidArgumentException;
use Nowo\UiKitBundle\Navigation\AreaNavRegistry;

/**
 * Page header: title, intro, toolbar actions, breadcrumbs (plain trail, or BreadcrumbKit when installed) and an
 * optional area navigation ({@see AreaNav}, rendered under the title through the `tabs` partial).
 * `breadcrumbsMode` (2.2): `auto` (default: BreadcrumbKit's trail when installed and not empty, else
 * {@see self::$breadcrumbs}), `kit` (BreadcrumbKit only) or `own` (always {@see self::$breadcrumbs}).
 * `back` (2.7): a {@see BackLink} rendered above the title through the `back_link` partial.
 * `status` (2.21): a {@see PageStatus} badge next to the title (`page_status` partial).
 * `actionsLayout` (2.21): `toolbar` (default: every action as a toolbar button, as before) or `split` — the
 * `header_actions` partial: icon buttons (actions with `display: icon`), at most one primary button and the square
 * "more" menu with the rest, destructive last ({@see HeaderActions}).
 */
final readonly class PageHeader
{
    public const BREADCRUMBS_MODES = ['auto', 'kit', 'own'];

    public const ACTIONS_LAYOUTS = ['toolbar', 'split'];

    /** @var list<Action> */
    public array $actions;

    /** @var list<Crumb> */
    public array $breadcrumbs;

    /**
     * @param iterable<Action> $actions
     * @param iterable<Crumb>  $breadcrumbs
     * @param string|null      $breadcrumbCollection BreadcrumbKit collection code (overrides the config default)
     * @param AreaNav|null     $tabs                 area navigation (tab bar between the sections of the area)
     * @param BackLink|null    $back                 "back" control above the title (2.7)
     * @param PageStatus|null  $status               status badge next to the title (2.21)
     * @param string           $actionsLayout        `toolbar` (default) or `split` (2.21, {@see HeaderActions})
     */
    public function __construct(
        public Label|string $title,
        public Label|string|null $intro = null,
        iterable $actions = [],
        iterable $breadcrumbs = [],
        public ?string $breadcrumbCollection = null,
        public ?AreaNav $tabs = null,
        public string $breadcrumbsMode = 'auto',
        public ?BackLink $back = null,
        public ?PageStatus $status = null,
        public string $actionsLayout = 'toolbar',
    ) {
        if (!\in_array($breadcrumbsMode, self::BREADCRUMBS_MODES, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid PageHeader breadcrumbsMode "%s" (%s).', $breadcrumbsMode, implode('|', self::BREADCRUMBS_MODES)));
        }
        if (!\in_array($actionsLayout, self::ACTIONS_LAYOUTS, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid PageHeader actionsLayout "%s" (%s).', $actionsLayout, implode('|', self::ACTIONS_LAYOUTS)));
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
        return new self($this->title, $this->intro, $actions, $this->breadcrumbs, $this->breadcrumbCollection, $this->tabs, $this->breadcrumbsMode, $this->back, $this->status, $this->actionsLayout);
    }

    /**
     * Copy with another area navigation (e.g. from {@see AreaNavRegistry}).
     *
     * @return self Fluent interface
     */
    public function withTabs(?AreaNav $tabs): self
    {
        return new self($this->title, $this->intro, $this->actions, $this->breadcrumbs, $this->breadcrumbCollection, $tabs, $this->breadcrumbsMode, $this->back, $this->status, $this->actionsLayout);
    }

    /**
     * Copy with another status badge (2.21; null removes it).
     *
     * @return self Fluent interface
     */
    public function withStatus(?PageStatus $status): self
    {
        return new self($this->title, $this->intro, $this->actions, $this->breadcrumbs, $this->breadcrumbCollection, $this->tabs, $this->breadcrumbsMode, $this->back, $status, $this->actionsLayout);
    }

    /**
     * Copy with the split actions layout (2.21): icon buttons, one primary button, the square "more" menu.
     *
     * @return self Fluent interface
     */
    public function withSplitActions(bool $split = true): self
    {
        return new self($this->title, $this->intro, $this->actions, $this->breadcrumbs, $this->breadcrumbCollection, $this->tabs, $this->breadcrumbsMode, $this->back, $this->status, $split ? 'split' : 'toolbar');
    }

    /**
     * The header actions split for the `header_actions` partial (2.21).
     */
    public function splitActions(): HeaderActions
    {
        return HeaderActions::split($this->actions);
    }
}
