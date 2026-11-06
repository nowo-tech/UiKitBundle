<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Shell;

use Stringable;

/**
 * Scheme guard for host-supplied link targets of the compact header (2.27: crumb hrefs, the home link, `page_actions`
 * hash items). Escaping keeps a value inside its attribute but does not stop `javascript:`; this keeps only relative
 * URLs (paths, `?query`, `#fragment`, `//host/path`) and the schemes `http`, `https` and `mailto`. The check runs on
 * the value as a browser reads it — tabs and line breaks removed anywhere, C0 controls and spaces trimmed at both
 * ends, scheme case-insensitive — so `JaVaScRiPt:` and `java<TAB>script:` are refused too. An accepted value is
 * returned unchanged (Twig escapes it when printing); a refused one gives null, and the caller renders no link.
 *
 * It checks the scheme only, not the origin: protocol-relative `//host/path` is accepted, and so are its backslash
 * forms `/\host/path` and `\\host/path`, which browsers read as `//host` (an http(s) link to another host). Hosts
 * that must keep links same-origin validate them before passing them in.
 *
 * {@see self::runsScript()} (2.34) is the narrower check of the two older places that print any scheme a host gives them
 * (`tree` nodes, `back_link`): it refuses only what runs in the page, so a `tel:` or `ftp:` link keeps working there.
 */
final class SafeHref
{
    public const SCHEMES = ['http', 'https', 'mailto'];

    public static function filter(?string $href): ?string
    {
        return null !== $href && self::isSafe($href) ? $href : null;
    }

    /** Schemes of an image source (2.32, `isSafeImage()`): no `mailto`. */
    public const IMAGE_SCHEMES = ['http', 'https'];

    /** Schemes that run in the page or in its origin when a link is followed (2.34, `runsScript()`). */
    public const SCRIPT_SCHEMES = ['javascript', 'vbscript', 'data', 'blob'];

    public static function isSafe(string $href): bool
    {
        return self::allows($href, self::SCHEMES);
    }

    /**
     * An image source (2.32: the shell brand icon): relative, `http` or `https` — the link check without `mailto`.
     */
    public static function isSafeImage(string $src): bool
    {
        return self::allows($src, self::IMAGE_SCHEMES);
    }

    /**
     * `isSafeImage()` as a filter: the value when it passes, else null.
     */
    public static function filterImage(?string $src): ?string
    {
        return null !== $src && self::isSafeImage($src) ? $src : null;
    }

    /**
     * Whether a link target runs script when followed (2.34): a {@see self::SCRIPT_SCHEMES} scheme, as a browser reads
     * it. Anything else, also a value that is not a string or an empty one, gives false.
     */
    public static function runsScript(mixed $href): bool
    {
        if (!\is_string($href) && !$href instanceof Stringable) {
            return false;
        }

        return \in_array(self::scheme(self::normalise((string) $href)), self::SCRIPT_SCHEMES, true);
    }

    /**
     * @param list<string> $schemes
     */
    private static function allows(string $href, array $schemes): bool
    {
        $normalised = self::normalise($href);
        if ('' === $normalised) {
            return false;
        }
        $scheme = self::scheme($normalised);

        return null === $scheme || \in_array($scheme, $schemes, true);
    }

    private static function normalise(string $href): string
    {
        return trim((string) preg_replace('/[\t\n\r]/', '', $href), "\x00..\x20");
    }

    /** Lower-case scheme of a normalised value, null when it is relative. */
    private static function scheme(string $normalised): ?string
    {
        return 1 === preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalised, $match) ? strtolower($match[1]) : null;
    }
}
