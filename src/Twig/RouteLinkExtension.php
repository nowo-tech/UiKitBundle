<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * nowo_ui_route_link(route = current route): the link configured for a route or route prefix
 * (`nowo_ui_kit.route_links`), e.g. the operator-manual chapter of a page for partials/_edge_tab.html.twig.
 *
 * A prefix matches the route itself or any route starting with "<prefix>_"; the longest matching prefix wins.
 * Stateless (configuration only), safe in long-running workers.
 */
final class RouteLinkExtension extends AbstractExtension
{
    /**
     * @param array<string, array{path: string, testid?: ?string}> $routes route or route prefix → link
     */
    public function __construct(
        private readonly array $routes = [],
        private readonly string $baseUrl = '',
        private readonly ?string $defaultTestid = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_ui_route_link', $this->link(...)),
        ];
    }

    /**
     * @return array{url: string, path: string, testid: ?string, prefix: string}|null the link of the route
     *                                                                                (current route when null), or null
     */
    public function link(?string $route = null): ?array
    {
        $route ??= $this->currentRoute();
        if (null === $route || '' === $route) {
            return null;
        }

        $best = null;
        foreach (array_keys($this->routes) as $prefix) {
            $prefix = (string) $prefix;
            if (($route === $prefix || str_starts_with($route, $prefix.'_')) && (null === $best || \strlen($prefix) > \strlen($best))) {
                $best = $prefix;
            }
        }
        if (null === $best) {
            return null;
        }

        $entry = $this->routes[$best];
        $path = $entry['path'];

        return [
            'url' => $this->isAbsolute($path) ? $path : $this->baseUrl.$path,
            'path' => $path,
            'testid' => $entry['testid'] ?? $this->defaultTestid,
            'prefix' => $best,
        ];
    }

    private function isAbsolute(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://');
    }

    private function currentRoute(): ?string
    {
        $route = $this->requestStack?->getCurrentRequest()?->attributes->get('_route');

        return \is_string($route) ? $route : null;
    }
}
