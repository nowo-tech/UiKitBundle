<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Area navigation (REQ-UI-003): the tab bar between the sections of one bundle area (e.g. CookieConsent
 * "Consents | Categories | Settings"), rendered by the `page_header` partial through the `tabs` partial
 * ({@see PageHeader::$tabs}). `label` is the `aria-label` of the `<nav>` (default `tabs.nav`), `attributes` its root
 * attributes (e.g. `data-turbo: 'false'`). Built by the bundle, or by an
 * {@see \Nowo\UiKitBundle\Navigation\AreaNavProviderInterface} (DashboardMenu and others consume the registry).
 */
final readonly class AreaNav
{
    /** @var list<AreaNavItem> */
    public array $items;

    /**
     * @param iterable<AreaNavItem>                     $items
     * @param array<string, string|int|float|bool|null> $attributes root attributes of the <nav> (no on* handlers)
     */
    public function __construct(
        iterable $items,
        public Label|string|null $label = null,
        public array $attributes = [],
    ) {
        $this->items = ViewList::of($items, AreaNavItem::class, 'AreaNav items');
        Action::assertAttributes($attributes);
    }

    public function isEmpty(): bool
    {
        return [] === $this->items;
    }

    /**
     * Copy where only the item with this href is active (e.g. a provider's navigation marked for the current page).
     *
     * @return self Fluent interface
     */
    public function withActive(string $href): self
    {
        return new self(array_map(static fn (AreaNavItem $item): AreaNavItem => $item->withActive($item->href === $href), $this->items), $this->label, $this->attributes);
    }

    /**
     * The active item, null when none is active.
     */
    public function active(): ?AreaNavItem
    {
        foreach ($this->items as $item) {
            if ($item->active) {
                return $item;
            }
        }

        return null;
    }
}
