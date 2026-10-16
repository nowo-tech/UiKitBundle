<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Definition-list section of a {@see ShowPage}.
 */
final readonly class DetailSection
{
    /** @var list<DetailItem> */
    public array $items;

    /**
     * @param iterable<DetailItem> $items
     */
    public function __construct(
        public Label|string|null $title = null,
        iterable $items = [],
        public ?string $id = null,
    ) {
        $this->items = ViewList::of($items, DetailItem::class, 'DetailSection items');
    }
}
