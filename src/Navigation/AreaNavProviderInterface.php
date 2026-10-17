<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Navigation;

use Nowo\UiKitBundle\View\AreaNav;

/**
 * Provides the area navigation of one or more areas (e.g. a bundle's admin area) to the {@see AreaNavRegistry}.
 * Autoconfigured with the tag `nowo_ui_kit.area_nav_provider` (or tag it yourself; the `priority` tag attribute
 * orders providers, highest first). Implementations must be stateless between requests (FrankenPHP worker): build
 * the navigation from the arguments and request-scoped services.
 */
interface AreaNavProviderInterface
{
    public const TAG = 'nowo_ui_kit.area_nav_provider';

    /**
     * Navigation of an area, null when this provider does not know the area.
     *
     * @param string               $area    area code (e.g. `cookie_consent`)
     * @param array<string, mixed> $context caller context (e.g. `current` href, route name)
     */
    public function areaNav(string $area, array $context = []): ?AreaNav;
}
