<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Confirm dialog of a destructive action (UiKit `_confirm` `<dialog>`; never inline `confirm()`).
 */
final readonly class Confirm
{
    public const VARIANTS = ['danger', 'primary', 'secondary'];

    public function __construct(
        public Label|string $title,
        public Label|string|null $message = null,
        public Label|string|null $confirmLabel = null,
        public string $variant = 'danger',
        public Label|string|null $cancelLabel = null,
        public ?string $id = null,
    ) {
        if (!\in_array($variant, self::VARIANTS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Confirm variant "%s" (%s).', $variant, implode('|', self::VARIANTS)));
        }
        if (null !== $id && 1 !== preg_match('/^[A-Za-z][A-Za-z0-9_:.-]*$/', $id)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Confirm id "%s".', $id));
        }
    }

    /**
     * Generic delete confirmation from UiKit's catalogue (`confirm.delete_title` / `confirm.delete_message`).
     *
     * @return self Fluent interface
     */
    public static function delete(Label|string|null $message = null): self
    {
        return new self(Label::kit('confirm.delete_title'), $message ?? Label::kit('confirm.delete_message'), Label::kit('action.delete'));
    }
}
