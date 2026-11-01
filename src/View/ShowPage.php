<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * View model of `@NowoUiKit/page/show.html.twig`: header + actions, detail sections (definition lists) and audit
 * meta. Bundles add `nowo_ui_show_sections` / `nowo_ui_show_after` (related tables).
 * `stats` (2.21): key figures ({@see Stat}) rendered as one compact row above the sections (`stat_row` partial).
 */
final readonly class ShowPage
{
    /** @var list<DetailSection> */
    public array $sections;

    /** @var list<DetailItem> */
    public array $meta;

    /** @var list<Stat> */
    public array $stats;

    /**
     * @param iterable<DetailSection>                   $sections
     * @param iterable<DetailItem>                      $meta       audit meta (created / updated by, …)
     * @param array<string, string|int|float|bool|null> $attributes extra attributes of the scaffold root (data-testid…; no on* handlers)
     * @param iterable<Stat>                            $stats      key figures above the sections (2.21)
     */
    public function __construct(
        public PageHeader $header,
        iterable $sections = [],
        iterable $meta = [],
        public ?string $bundle = null,
        public ?string $layout = null,
        public array $attributes = [],
        iterable $stats = [],
    ) {
        Action::assertAttributes($attributes);
        $this->stats = ViewList::of($stats, Stat::class, 'ShowPage stats');
        $this->sections = ViewList::of($sections, DetailSection::class, 'ShowPage sections');
        $this->meta = ViewList::of($meta, DetailItem::class, 'ShowPage meta');
    }
}
