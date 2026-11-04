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
 */
final class SafeHref
{
    public const SCHEMES = ['http', 'https', 'mailto'];

    public static function filter(?string $href): ?string
    {
        return null !== $href && self::isSafe($href) ? $href : null;
    }

    public static function isSafe(string $href): bool
    {
        $normalised = trim((string) preg_replace('/[\t\n\r]/', '', $href), "\x00..\x20");
        if ('' === $normalised) {
            return false;
        }
        if (1 !== preg_match('/^([a-z][a-z0-9+.\-]*):/i', $normalised, $match)) {
            return true;
        }

        return \in_array(strtolower($match[1]), self::SCHEMES, true);
    }
}
