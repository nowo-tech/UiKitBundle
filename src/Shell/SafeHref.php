<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Shell;

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
     * @param list<string> $schemes
     */
    private static function allows(string $href, array $schemes): bool
    {
        $normalised = trim((string) preg_replace('/[\t\n\r]/', '', $href), "\x00..\x20");
        if ('' === $normalised) {
            return false;
        }
        if (1 !== preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalised, $match)) {
            return true;
        }

        return \in_array(strtolower($match[1]), $schemes, true);
    }
}
