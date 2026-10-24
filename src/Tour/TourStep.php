<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tour;

use Nowo\UiKitBundle\View\Label;

/**
 * One step of a guided {@see Tour} (2.13): a popover anchored to the element matching `selector`, or centred on the
 * page when `selector` is null (welcome / overview steps). The `tour` partial translates `title` / `description`
 * (a {@see Label}, or a string printed as is) and the Stimulus peer `product-tour` skips a step whose selector matches
 * nothing on the page, so permission-dependent markup needs no matching server-side filter.
 */
final readonly class TourStep
{
    /** Popover sides driver.js accepts. */
    public const SIDES = ['top', 'right', 'bottom', 'left'];

    /** Popover alignments driver.js accepts. */
    public const ALIGNS = ['start', 'center', 'end'];

    /**
     * @param string|null $selector CSS selector of the highlighted element (e.g. `[data-tour="sidebar"]`); null = centred popover
     * @param string|null $side     one of {@see self::SIDES}; null = driver.js default
     * @param string|null $align    one of {@see self::ALIGNS}; null = driver.js default
     */
    public function __construct(
        public ?string $selector,
        public Label|string $title,
        public Label|string $description = '',
        public ?string $side = null,
        public ?string $align = null,
    ) {
        if (null !== $selector && '' === trim($selector)) {
            throw new \InvalidArgumentException('A TourStep selector must be null (centred step) or a non-empty CSS selector.');
        }
        if (null !== $side && !\in_array($side, self::SIDES, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid TourStep side "%s" (expected one of: %s).', $side, implode(', ', self::SIDES)));
        }
        if (null !== $align && !\in_array($align, self::ALIGNS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid TourStep align "%s" (expected one of: %s).', $align, implode(', ', self::ALIGNS)));
        }
    }

    /**
     * Selector of a `data-tour` anchor: `TourStep::anchor('sidebar')` → `[data-tour="sidebar"]`.
     */
    public static function anchor(string $name): string
    {
        if (1 !== preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]*$/', $name)) {
            throw new \InvalidArgumentException(\sprintf('Invalid tour anchor name "%s".', $name));
        }

        return '[data-tour="'.$name.'"]';
    }
}
