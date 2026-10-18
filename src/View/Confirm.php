<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Confirm dialog of a destructive action (UiKit `_confirm` `<dialog>`; never inline `confirm()`).
 *
 * `expectedValue` turns it into a type-to-confirm dialog: the confirm button stays disabled until the user types
 * exactly that value (nowo-ui-confirm.js; the input also carries `required` + `pattern`, so the browser blocks the
 * submit without JS on the full-page confirm). `expectedLabel` replaces the prompt (default
 * `confirm.type_to_confirm` with `%value%`).
 *
 * `messageInHeader` renders the message inside the header, under the title (hosts whose dialog header is title +
 * message); null = the theme option `theme.confirm_message_in_header` (default false: message in the body).
 * `size` (2.2): `sm` | `md` (default, 28rem) | `lg` width of the confirm dialog.
 * Titles should name the item ("Delete order #1042?"): pass `title` to {@see self::delete()} (2.2) or build the
 * Confirm with a {@see Label} carrying the item as a parameter.
 */
final readonly class Confirm implements \JsonSerializable
{
    public const VARIANTS = ['danger', 'primary', 'secondary'];

    /** Dialog widths (2.2): sm = 22rem, md = 28rem (default), lg = 40rem. */
    public const SIZES = ['sm', 'md', 'lg'];

    public function __construct(
        public Label|string $title,
        public Label|string|null $message = null,
        public Label|string|null $confirmLabel = null,
        public string $variant = 'danger',
        public Label|string|null $cancelLabel = null,
        public ?string $id = null,
        public ?string $expectedValue = null,
        public Label|string|null $expectedLabel = null,
        public ?bool $messageInHeader = null,
        public string $size = 'md',
    ) {
        if (!\in_array($size, self::SIZES, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Confirm size "%s" (%s).', $size, implode('|', self::SIZES)));
        }
        if (!\in_array($variant, self::VARIANTS, true)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Confirm variant "%s" (%s).', $variant, implode('|', self::VARIANTS)));
        }
        if (null !== $id && 1 !== preg_match('/^[A-Za-z][A-Za-z0-9_:.-]*$/', $id)) {
            throw new \InvalidArgumentException(\sprintf('Invalid Confirm id "%s".', $id));
        }
        if (null !== $expectedValue && '' === trim($expectedValue)) {
            throw new \InvalidArgumentException('A Confirm expectedValue must not be empty.');
        }
    }

    /**
     * HTML `pattern` matching exactly {@see self::$expectedValue} (regex syntax characters escaped; valid with the
     * `v` flag browsers compile `pattern` with). Null without an expected value.
     */
    public function expectedPattern(): ?string
    {
        if (null === $this->expectedValue) {
            return null;
        }

        return (string) preg_replace('/[\\\\^$.*+?()[\]{}|\/]/', '\\\\$0', $this->expectedValue);
    }

    /**
     * Generic delete confirmation from UiKit's catalogue (`confirm.delete_title` / `confirm.delete_message`).
     * `title` (2.2) replaces the generic title, e.g. with the item: `Confirm::delete($message, 'Delete order #1042?')`.
     *
     * @return self Fluent interface
     */
    public static function delete(Label|string|null $message = null, Label|string|null $title = null): self
    {
        return new self($title ?? Label::kit('confirm.delete_title'), $message ?? Label::kit('confirm.delete_message'), Label::kit('action.delete'));
    }

    /**
     * Public state for {@see Action::confirmKey()} (derived dialog ids): the default `size` is left out, so ids
     * derived by 2.0 / 2.1 stay the same.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $vars = get_object_vars($this);
        if ('md' === $this->size) {
            unset($vars['size']);
        }

        return $vars;
    }
}
