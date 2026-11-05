<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;

/**
 * Redirect targets of the remote form dialog (2.28): a path-absolute URL (one leading `/`, no `//`, no backslash, no
 * control character, no `.` / `..` segment, also `%2e`-encoded, at most 2048 characters), or an absolute URL on the request's own scheme, host and port, which
 * is reduced to its path, query and fragment. The client applies the same rule to `X-Nowo-Redirect`.
 *
 * @internal
 */
final class RemoteDialogTarget
{
    public const MAX_LENGTH = 2048;

    /**
     * @throws InvalidArgumentException when the URL is not a same-origin target (a programming error)
     */
    public static function path(string $url, ?Request $request): string
    {
        $path = self::tryPath($url, $request);
        if (null === $path) {
            throw new InvalidArgumentException('A remote dialog redirect must be a same-origin path (e.g. "/orders/7"), not another origin, a protocol-relative URL or a non-HTTP scheme.');
        }

        return $path;
    }

    /**
     * Whether a raw path holds a dot segment (`.`, `..`, or their `%2e` forms, either case) or becomes `//…` / holds a
     * backslash once decoded. Browsers normalise dot segments, so `/.//evil.example` resolves to `//evil.example`
     * (another origin): such targets are refused, never normalised (REQ-SEC-004 ES-M1). Decoded once, both forms
     * checked.
     */
    public static function hasUnsafeSegments(string $path): bool
    {
        // An encoded dot has no business in a redirect path (KIT-68): refused anywhere, either case.
        if (false !== stripos($path, '%2e')) {
            return true;
        }
        $decoded = rawurldecode($path);
        if (str_starts_with($decoded, '//') || str_contains($decoded, '\\')) {
            return true;
        }
        foreach ([$path, $decoded] as $candidate) {
            foreach (explode('/', $candidate) as $segment) {
                if (1 === preg_match('/^(?:\.|%2e){1,2}$/i', $segment)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** The path part of a path-absolute URL (before `?` / `#`). */
    private static function rawPath(string $url): string
    {
        return (string) strtok($url, '?#');
    }

    /** The same-origin path of a URL, or null. */
    public static function tryPath(string $url, ?Request $request): ?string
    {
        if ('' === $url || \strlen($url) > self::MAX_LENGTH || 1 === preg_match('/[\x00-\x1F\x7F\\\\]/', $url)) {
            return null;
        }
        if (str_starts_with($url, '/')) {
            return str_starts_with($url, '//') || self::hasUnsafeSegments(self::rawPath($url)) ? null : $url;
        }
        if (!$request instanceof Request) {
            return null;
        }
        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? ('https' === $scheme ? 443 : 80);
        if (!\in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])
            || $scheme !== $request->getScheme() || strtolower($parts['host']) !== strtolower($request->getHost()) || $port !== $request->getPort()) {
            return null;
        }
        $path = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];
        if (str_starts_with($path, '//') || self::hasUnsafeSegments($path)) {
            return null;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }
}
