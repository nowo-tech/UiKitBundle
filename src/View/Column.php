<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Table column of a {@see ListPage}. The default `nowo_ui_list_row` block prints `row[key]` / `row.key`;
 * bundles override the block for rich cells.
 */
final readonly class Column
{
    public const ALIGNS = ['start', 'center', 'end'];

    public function __construct(
        public string $key,
        public Label|string|null $label = null,
        public bool $sortable = false,
        public string $align = 'start',
        public ?string $class = null,
        public bool $required = false,
    ) {
        if ('' === trim($key)) {
            throw new \InvalidArgumentException('A Column needs a non-empty key.');
        }
        if (!\in_array($align, self::ALIGNS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Column align "%s" (%s).', $align, implode('|', self::ALIGNS)));
        }
    }
}
