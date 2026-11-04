<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Avatar;

/**
 * 2.26 avatar URL policy: the only picture URLs the `_avatar` partial (and the bundles that render a person through it)
 * put in `<img src>`.
 *
 * Accepted:
 * - `https://host/…`: scheme `https` (any case), a non-empty host and no userinfo (`https://good@evil` is rejected);
 * - a same-origin path that starts with exactly one `/` (`/files/<uuid>/s64`).
 *
 * Rejected (null): every other scheme (`http:`, `javascript:`, `vbscript:`, `blob:`, `file:`…), `data:` of any kind
 * (including `data:image/…`), protocol-relative `//host`, any backslash, relative paths, IPv6-literal hosts, ports
 * above 65535, ASCII control characters and whitespace (`[\x00-\x20\x7F]`, also a trailing line feed), quotes, `<`,
 * `>` and backtick, the empty string and anything longer than {@see self::MAX_BYTES} bytes.
 *
 * The URL must be stable for the page's life and access-checked when the browser fetches it (no bearer secret): the
 * host owns it, the kit only filters it. Stateless and pure, safe in long-running workers.
 */
final class AvatarUrlPolicy
{
    public const MAX_BYTES = 2048;

    /**
     * The whole policy as one PCRE (byte mode, case-insensitive, `\z`: no trailing line feed): 1 to 2048 bytes without
     * control characters, whitespace, backslash, quotes, `<`, `>` or backtick (a valid URL has them percent-encoded),
     * then a same-origin path (`/` not followed by `/`) or `https://` with a host free of userinfo and of IPv6 brackets
     * and an optional port from 0 to 65535. `partials/_avatar.html.twig` applies it through Twig's core `constant()` +
     * `matches`, so the partial works in any Twig environment (no extension needed) with the same rules; the client
     * check of `nowo-ui-avatar.js` mirrors it (tests/Fixtures/avatar/url-policy-cases.json is shared by both).
     */
    public const PATTERN = '#^(?=[^\x00-\x20\x7F\\\\"\'<>`]{1,2048}\z)(?:/(?!/)|https://[^/?\#@:\[\]]+(?::(?:6553[0-5]|655[0-2][0-9]|65[0-4][0-9]{2}|6[0-4][0-9]{3}|[1-5][0-9]{4}|[0-9]{1,4}))?(?:[/?\#]|\z))#i';

    /**
     * The URL when the policy accepts it, else null (the caller renders the initials).
     */
    public static function sanitize(?string $url): ?string
    {
        return null !== $url && 1 === preg_match(self::PATTERN, $url) ? $url : null;
    }

    /**
     * Whether the policy accepts the URL.
     */
    public static function isAllowed(?string $url): bool
    {
        return null !== self::sanitize($url);
    }
}
