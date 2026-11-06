<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Shell;

/**
 * Where the app shell's aside sits on desktop (2.32): `below-header` (the default and the only layout up to 2.31: the
 * header across the top, the aside under it, the footer under both) or `full-height` (the aside from the top edge to the
 * bottom with the burger and the brand in its head; the header and the footer start at its right edge). Phones (below
 * the kit's sidebar breakpoint, 901px) keep the off-canvas aside in both.
 *
 * Resolution, first match wins ({@see SidebarLayoutResolver}): the `sidebar_layout` option of `app_shell_open`, then
 * the request attribute {@see self::ATTRIBUTE} (set by the host per request, e.g. from the user's preference in a
 * `kernel.request` listener), then the config `nowo_ui_kit.shell.sidebar_layout`.
 */
enum SidebarLayout: string
{
    case BelowHeader = 'below-header';
    case FullHeight = 'full-height';

    /** Request attribute the host sets per request (a case or its string value; anything else is ignored). */
    public const ATTRIBUTE = '_nowo_ui_sidebar_layout';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * A case, or its string value; anything else (missing, invalid) gives null, so the next source applies.
     */
    public static function fromValue(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return \is_string($value) ? self::tryFrom($value) : null;
    }
}
