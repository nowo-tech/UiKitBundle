<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * View model of `@NowoUiKit/page/show.html.twig`: header + actions, detail sections (definition lists) and audit
 * meta. Bundles add `nowo_ui_show_sections` / `nowo_ui_show_after` (related tables).
 */
final readonly class ShowPage
{
    /** @var list<DetailSection> */
    public array $sections;

    /** @var list<DetailItem> */
    public array $meta;

    /**
     * @param iterable<DetailSection> $sections
     * @param iterable<DetailItem>    $meta     audit meta (created / updated by, …)
     */
    public function __construct(
        public PageHeader $header,
        iterable $sections = [],
        iterable $meta = [],
        public ?string $bundle = null,
        public ?string $layout = null,
    ) {
        $this->sections = ViewList::of($sections, DetailSection::class, 'ShowPage sections');
        $this->meta = ViewList::of($meta, DetailItem::class, 'ShowPage meta');
    }
}
