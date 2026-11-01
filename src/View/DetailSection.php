<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Definition-list section of a {@see ShowPage}. `layout` (2.21): `list` (default: term | value rows) or `facts` — a
 * compact grid of label-over-value cells, several per row (`detail_list` partial with `modifier: 'facts'`, slot
 * `detail_list_facts`).
 */
final readonly class DetailSection
{
    public const LAYOUTS = ['list', 'facts'];

    /** @var list<DetailItem> */
    public array $items;

    /**
     * @param iterable<DetailItem> $items
     */
    public function __construct(
        public Label|string|null $title = null,
        iterable $items = [],
        public ?string $id = null,
        public string $layout = 'list',
    ) {
        if (!\in_array($layout, self::LAYOUTS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid DetailSection layout "%s" (%s).', $layout, implode('|', self::LAYOUTS)));
        }
        $this->items = ViewList::of($items, DetailItem::class, 'DetailSection items');
    }
}
