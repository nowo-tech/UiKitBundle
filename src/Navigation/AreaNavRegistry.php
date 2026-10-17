<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Navigation;

use Nowo\UiKitBundle\View\AreaNav;

/**
 * Area navigation of the tagged {@see AreaNavProviderInterface} services: the first provider (priority order)
 * that knows the area wins. With `context.current` (href) only that item is marked active.
 */
final readonly class AreaNavRegistry
{
    /**
     * @param iterable<AreaNavProviderInterface> $providers
     */
    public function __construct(
        private iterable $providers = [],
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function get(string $area, array $context = []): ?AreaNav
    {
        foreach ($this->providers as $provider) {
            $nav = $provider->areaNav($area, $context);
            if ($nav instanceof AreaNav) {
                $current = $context['current'] ?? null;

                return \is_string($current) ? $nav->withActive($current) : $nav;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function has(string $area, array $context = []): bool
    {
        return $this->get($area, $context) instanceof AreaNav;
    }
}
