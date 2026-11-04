<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Shell;

use InvalidArgumentException;
use Nowo\UiKitBundle\Twig\ThemeExtension;

/**
 * Presentation of the page header for one request (2.27, compact header): `standard` (the page header with its title,
 * intro, breadcrumbs and actions) or `compact` (the breadcrumb and the page actions move into the top bar; the page
 * header keeps a visually hidden `h1`).
 *
 * Resolution, first match wins ({@see ThemeExtension::headerPresentation()}): the explicit
 * `presentation` option, then the request attribute {@see self::ATTRIBUTE} (set by the host per request, e.g. from the
 * user's preference in a `kernel.request` listener; request scope keeps it safe in FrankenPHP workers), then the
 * config `nowo_ui_kit.header.presentation`.
 */
enum HeaderPresentation: string
{
    case Standard = 'standard';
    case Compact = 'compact';

    /** Request attribute the host sets per request (a case or its string value; anything else is ignored). */
    public const ATTRIBUTE = '_nowo_ui_header_presentation';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /**
     * A request attribute value: a case, or its string value. Anything else (missing, invalid) gives null, so the
     * config default applies.
     */
    public static function fromRequestValue(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        return \is_string($value) ? self::tryFrom($value) : null;
    }

    /**
     * An explicit option: a case or its string value. An invalid value is a template error and throws.
     *
     * @throws InvalidArgumentException
     */
    public static function fromOption(self|string $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        return self::tryFrom($value) ?? throw new InvalidArgumentException(\sprintf('Invalid header presentation "%s" (%s).', $value, implode('|', self::values())));
    }
}
