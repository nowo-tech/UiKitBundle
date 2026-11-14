<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

/**
 * 2.49 (REQ-ARCH-001 rule 3): framework-free context of one listing page view, the input of
 * {@see ListingPreferenceReaderInterface::load()}.
 *
 *  - `cookies`: the listing cookies of the request (name => value, strings only; the kit copies only
 *    `nowo_ui_listing_*` names, never session or remember-me cookies), so a browser-side store such as the default
 *    {@see CookieListingPreferenceStore} needs no HttpFoundation `Request`;
 *  - `viewerId`: an opaque id of the viewer (user identifier, user id…) for per-user stores, null when unknown;
 *    filled from {@see ListingViewerResolverInterface} when the host provides one, or by a DDD host that builds the
 *    context itself;
 *  - `route` / `routeParameters` / `locale`: the current route name, its scalar parameters (a project id in the path…)
 *    and the locale, when known.
 *
 * Immutable and request-scoped: the kit builds a new instance per call, never keeps one in a service.
 */
final readonly class ListingContext
{
    /** @var array<string, string> */
    public array $cookies;

    /** @var array<string, bool|float|int|string|null> */
    public array $routeParameters;

    /**
     * @param array<array-key, mixed> $cookies         non-string names or values are dropped
     * @param array<array-key, mixed> $routeParameters non-string names or non-scalar values are dropped
     */
    public function __construct(
        array $cookies = [],
        public ?string $viewerId = null,
        public ?string $route = null,
        array $routeParameters = [],
        public ?string $locale = null,
    ) {
        $clean = [];
        foreach ($cookies as $name => $value) {
            if (\is_string($name) && \is_string($value)) {
                $clean[$name] = $value;
            }
        }
        $this->cookies = $clean;
        $params = [];
        foreach ($routeParameters as $name => $value) {
            if (\is_string($name) && (null === $value || \is_scalar($value))) {
                $params[$name] = $value;
            }
        }
        $this->routeParameters = $params;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function withViewerId(?string $viewerId): self
    {
        return new self($this->cookies, $viewerId, $this->route, $this->routeParameters, $this->locale);
    }
}
