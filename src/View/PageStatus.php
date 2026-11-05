<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use InvalidArgumentException;

/**
 * Status of the record a page shows (2.21, {@see PageHeader::$status}): a badge next to the page title — e.g. "Up",
 * "Paused", "Failing". The label is translated by the host ({@see Label} or a string); `tone` picks the badge slot
 * (`badge_<tone>`), `title` an optional tooltip (last check, since when…). Rendered by the `page_status` partial.
 *
 * 2.23: `attributes` adds host attributes to the badge (e.g. `data-detail-status` that a live script updates);
 * validated by {@see Action::assertAttributes()} (no `on*`), rendered escaped. Empty (default): unchanged markup.
 */
final readonly class PageStatus
{
    public const TONES = ['success', 'danger', 'warning', 'neutral', 'info'];

    /**
     * @param array<string, string|int|float|bool|null> $attributes extra attributes of the badge (2.23; no on*)
     */
    public function __construct(
        public Label|string $label,
        public string $tone = 'neutral',
        public Label|string|null $title = null,
        public array $attributes = [],
    ) {
        Action::assertAttributes($attributes);
        if (!\in_array($tone, self::TONES, true)) {
            throw new InvalidArgumentException(\sprintf('Invalid PageStatus tone "%s" (%s).', $tone, implode('|', self::TONES)));
        }
    }

    /**
     * The `status` option of the `dialog` / `confirm_dialog` partials (2.29, Twig `nowo_ui_dialog_status()`): a
     * PageStatus as is; a hash `{label, tone, title, attributes}` with a non-empty `label` (string or {@see Label}) as a
     * PageStatus whose `tone` is one of {@see self::TONES} — any other tone (unknown, `primary`, not a string) becomes
     * `neutral`; anything else (a string, a number, an object, a hash without a label — e.g. a `status` variable
     * inherited from the template context) is ignored: null, no badge. Hash `attributes` are validated like the
     * constructor's (no on*).
     */
    public static function fromOption(mixed $status): ?self
    {
        if ($status instanceof self) {
            return $status;
        }
        if (!\is_array($status)) {
            return null;
        }
        $label = $status['label'] ?? null;
        if (!$label instanceof Label && (!\is_string($label) || '' === $label)) {
            return null;
        }
        $tone = $status['tone'] ?? null;
        $title = $status['title'] ?? null;
        /** @var array<string, string|int|float|bool|null> $attributes */
        $attributes = \is_array($status['attributes'] ?? null) ? $status['attributes'] : [];

        return new self(
            $label,
            \is_string($tone) && \in_array($tone, self::TONES, true) ? $tone : 'neutral',
            $title instanceof Label || \is_string($title) ? $title : null,
            $attributes,
        );
    }
}
