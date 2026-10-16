<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Table column of a {@see ListPage}. The default `nowo_ui_list_row` block prints `row[key]` / `row.key`;
 * bundles override the block for rich cells.
 *
 * `priority` (responsive tables, `ListPage::$responsive = 'cards'`): null / 1 = always shown, 2 = hidden when the
 * table container is narrower than 48rem, 3 = hidden below 60rem; `required` columns never hide. In card mode
 * (container below 40rem) every column shows again, labelled with `label`.
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
        public ?int $priority = null,
    ) {
        if (null !== $priority && ($priority < 1 || $priority > 3)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Column priority %d (1 = always shown, 2 = hidden below 48rem, 3 = hidden below 60rem of table width).', $priority));
        }
        if ('' === trim($key)) {
            throw new \InvalidArgumentException('A Column needs a non-empty key.');
        }
        if (!\in_array($align, self::ALIGNS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Column align "%s" (%s).', $align, implode('|', self::ALIGNS)));
        }
    }
}
