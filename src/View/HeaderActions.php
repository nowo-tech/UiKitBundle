<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * The actions of a page header split for the `header_actions` partial (2.21, {@see PageHeader::$actionsLayout}
 * `split`):
 *
 * - `icons`: every visible action with `display: icon` ({@see Action::asIcon()}), in their order — square icon
 *   buttons with `aria-label` + `title`;
 * - `primary`: the first other action whose resolved variant is `primary` (Create, Save…) — at most one filled button;
 * - `more`: everything else for the square "more" menu, in their order, destructive actions (variant `danger`) last.
 *
 * Hidden actions (`visible: false`) are dropped. POST / confirm / dialog actions keep working: the menu draws them as
 * items and the confirm dialogs of `more` ({@see self::confirmActions()}) render outside the popover panel.
 */
final readonly class HeaderActions
{
    /** @var list<Action> */
    public array $icons;

    /** @var list<Action> */
    public array $more;

    /**
     * @param iterable<Action> $icons
     * @param iterable<Action> $more
     */
    public function __construct(
        public ?Action $primary = null,
        iterable $icons = [],
        iterable $more = [],
    ) {
        $this->icons = ViewList::of($icons, Action::class, 'HeaderActions icons');
        $this->more = ViewList::of($more, Action::class, 'HeaderActions more');
    }

    /**
     * Splits a page header's actions (see the class comment for the rules).
     *
     * @param iterable<mixed> $actions View\Action objects (anything else is rejected)
     */
    public static function split(iterable $actions): self
    {
        $primary = null;
        $icons = [];
        $more = [];
        $danger = [];
        foreach (ViewList::of($actions, Action::class, 'HeaderActions actions') as $action) {
            if (!$action->visible) {
                continue;
            }
            $variant = $action->resolvedVariant();
            if ('icon' === $action->display) {
                $icons[] = $action;
            } elseif ('primary' === $variant && null === $primary) {
                $primary = $action;
            } elseif ('danger' === $variant) {
                $danger[] = $action;
            } else {
                $more[] = $action;
            }
        }

        return new self($primary, $icons, [...$more, ...$danger]);
    }

    public function isEmpty(): bool
    {
        return !$this->primary instanceof Action && [] === $this->icons && [] === $this->more;
    }

    /**
     * The `more` actions with a Confirm: their dialogs render after the menu, outside the popover panel (a closed
     * popover would hide them), while the menu items only print the toggles (`confirm_external`).
     *
     * @return list<Action>
     */
    public function confirmActions(): array
    {
        return array_values(array_filter($this->more, static fn (Action $action): bool => null !== $action->confirmId()));
    }
}
