<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use InvalidArgumentException;

/**
 * Status of the record a page shows (2.21, {@see PageHeader::$status}): a badge next to the page title — e.g. "Up",
 * "Paused", "Failing". The label is translated by the host ({@see Label} or a string); `tone` picks the badge slot
 * (`badge_<tone>`), `title` an optional tooltip (last check, since when…). Rendered by the `page_status` partial.
 */
final readonly class PageStatus
{
    public const TONES = ['success', 'danger', 'warning', 'neutral', 'info'];

    public function __construct(
        public Label|string $label,
        public string $tone = 'neutral',
        public Label|string|null $title = null,
    ) {
        if (!\in_array($tone, self::TONES, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid PageStatus tone "%s" (%s).', $tone, implode('|', self::TONES)));
        }
    }
}
