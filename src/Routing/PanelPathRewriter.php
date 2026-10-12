<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Routing;

use Symfony\Component\Routing\RouteCollection;

/**
 * Rewrites path prefixes (for example `/admin/blog` → `/panel/blog`) using a configurable map.
 *
 * Prefixes are matched on path-segment boundaries (end of string, `/` or `{`) and the longest
 * prefix always wins. An empty map is a no-op.
 */
final class PanelPathRewriter
{
    /**
     * Prefix map sorted by descending source-prefix length.
     *
     * @var array<string, string>
     */
    private readonly array $map;

    /**
     * @param array<string, string> $map Legacy prefix → new prefix (no trailing slash)
     */
    public function __construct(array $map = [])
    {
        $normalized = [];
        foreach ($map as $from => $to) {
            $from = $this->normalizePrefix((string) $from);
            $to = $this->normalizePrefix($to);
            if ('' === $from || $from === $to) {
                continue;
            }

            $normalized[$from] = $to;
        }

        uksort($normalized, static fn (string $a, string $b): int => \strlen($b) <=> \strlen($a) ?: strcmp($a, $b));

        $this->map = $normalized;
    }

    /**
     * @return array<string, string>
     */
    public function getMap(): array
    {
        return $this->map;
    }

    public function isEmpty(): bool
    {
        return [] === $this->map;
    }

    /**
     * Rewrite the path of every route in the collection (in place).
     */
    public function rewriteCollection(RouteCollection $collection): void
    {
        if ($this->isEmpty()) {
            return;
        }

        foreach ($collection->all() as $route) {
            $path = $route->getPath();
            foreach ($this->map as $from => $to) {
                if ($this->matches($path, $from, true)) {
                    $route->setPath($to.substr($path, \strlen($from)));

                    break;
                }
            }
        }
    }

    /**
     * Map a legacy request path to its new path, or null when unchanged.
     */
    public function legacyRedirectTarget(string $requestPath): ?string
    {
        foreach ($this->map as $from => $to) {
            if ($this->matches($requestPath, $from, false)) {
                return $to.substr($requestPath, \strlen($from));
            }
        }

        return null;
    }

    private function matches(string $path, string $prefix, bool $allowPlaceholder): bool
    {
        if (!str_starts_with($path, $prefix)) {
            return false;
        }

        $next = substr($path, \strlen($prefix), 1);

        return '' === $next || '/' === $next || ($allowPlaceholder && '{' === $next);
    }

    private function normalizePrefix(string $prefix): string
    {
        $prefix = trim($prefix);
        if ('' === $prefix) {
            return '';
        }

        return '/'.trim($prefix, '/');
    }
}
