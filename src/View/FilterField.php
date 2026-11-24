<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use InvalidArgumentException;

/**
 * Per-field options of a {@see ListPage} filter (keyed by the form child name): placeholder, width and classes
 * applied by the `list_filters` partial through `form_row()` (`attr` / `row_attr`); the row also gets the
 * `filters_field` slot.
 *
 * `width`: `xs` | `sm` | `md` | `lg` | `full` (class `nowo-ui-filters__field--<width>` on the row). In the list
 * scaffold's filter grid (2.2) every field stretches to its grid cell: `xs` / `sm` / `md` / null span one column
 * (min `--nowo-ui-filters-field-min`, 12rem), `lg` spans two (from 48rem of viewport), `full` the whole row.
 *
 * 2.72 placement (default: the field grid, the 2.71 output):
 * - `more: true` folds the field under the "More filters" disclosure (`<details class="nowo-ui-filters-more
 *   filters-more">`) rendered after the actions; it shows how many of its fields are set and stays open while one is.
 * - `pager: true` renders the field compact (natural width) as the first item of the actions group, right before the
 *   Filter / Clear buttons (class `nowo-ui-filters__pager` + `rowClass`, e.g. a host's own pager class): meant for a
 *   "N per page" select. A field cannot be both.
 */
final readonly class FilterField
{
    public const WIDTHS = ['xs', 'sm', 'md', 'lg', 'full'];

    /**
     * @param array<string, string|int|float|bool|null> $attributes    extra widget attributes (no on* handlers)
     * @param array<string, string|int|float|bool|null> $rowAttributes extra row attributes (no on* handlers)
     */
    public function __construct(
        public string $name,
        public Label|string|null $placeholder = null,
        public ?string $width = null,
        public ?string $class = null,
        public ?string $rowClass = null,
        public array $attributes = [],
        public array $rowAttributes = [],
        public bool $more = false,
        public bool $pager = false,
    ) {
        if ($more && $pager) {
            throw new InvalidArgumentException(\sprintf('FilterField "%s" cannot be both "more" and "pager".', $name));
        }
        if (1 !== preg_match('/^[A-Za-z0-9_][A-Za-z0-9_-]*$/', $name)) {
            throw new InvalidArgumentException(\sprintf('Invalid FilterField name "%s" (the form child name).', $name));
        }
        if (null !== $width && !\in_array($width, self::WIDTHS, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid FilterField width "%s" (%s).', $width, implode('|', self::WIDTHS)));
        }
        Action::assertAttributes($attributes);
        Action::assertAttributes($rowAttributes);
    }
}
