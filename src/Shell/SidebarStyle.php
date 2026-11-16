<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Shell;

/**
 * How the app shell paints its chrome on desktop (2.52): `classic` (the default and the only style up to 2.51: the
 * header, the aside and the footer on the surface with borders between them, the content on the muted surface) or
 * `inset` (modelled on GitLab: the header, the aside and the footer share one shell surface with no border or
 * separator between them, and the content sits in an inset panel with rounded corners, a small gap from the aside, the
 * header and the viewport edges and its own surface). Phones (below the kit's sidebar breakpoint, 901px) render the
 * same in both styles, with no inset. It works with both {@see SidebarLayout} layouts.
 *
 * Resolution, first match wins ({@see SidebarStyleResolver}): the `sidebar_style` option of `app_shell_open`, then the
 * request attribute {@see self::ATTRIBUTE} (set by the host per request, e.g. from the user's preference in a
 * `kernel.request` listener), then the config `nowo_ui_kit.shell.sidebar_style`.
 */
enum SidebarStyle: string
{
    case Classic = 'classic';
    case Inset = 'inset';

    /** Request attribute the host sets per request (a case or its string value; anything else is ignored). */
    public const ATTRIBUTE = '_nowo_ui_sidebar_style';

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
