<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Per-field options of a {@see ListPage} filter (keyed by the form child name): placeholder, width and classes
 * applied by the `list_filters` partial through `form_row()` (`attr` / `row_attr`); the row also gets the
 * `filters_field` slot.
 *
 * `width`: `xs` | `sm` | `md` | `lg` | `full` (class `nowo-ui-filters__field--<width>` on the row). In the list
 * scaffold's filter grid (2.2) every field stretches to its grid cell: `xs` / `sm` / `md` / null span one column
 * (min `--nowo-ui-filters-field-min`, 12rem), `lg` spans two (from 48rem of viewport), `full` the whole row.
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
    ) {
        if (1 !== preg_match('/^[A-Za-z0-9_][A-Za-z0-9_-]*$/', $name)) {
            throw new \InvalidArgumentException(\sprintf('Invalid FilterField name "%s" (the form child name).', $name));
        }
        if (null !== $width && !\in_array($width, self::WIDTHS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid FilterField width "%s" (%s).', $width, implode('|', self::WIDTHS)));
        }
        Action::assertAttributes($attributes);
        Action::assertAttributes($rowAttributes);
    }
}
