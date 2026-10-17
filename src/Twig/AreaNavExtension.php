<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

use Nowo\UiKitBundle\Navigation\AreaNavRegistry;
use Nowo\UiKitBundle\View\AreaNav;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * `nowo_ui_area_nav(area, context = {})`: the {@see AreaNav} of an area from the {@see AreaNavRegistry} (null when no
 * provider knows it), e.g. `PageHeader` built in Twig or a host menu.
 */
final class AreaNavExtension extends AbstractExtension
{
    public function __construct(
        private readonly AreaNavRegistry $registry,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_ui_area_nav', $this->areaNav(...)),
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    public function areaNav(string $area, array $context = []): ?AreaNav
    {
        return $this->registry->get($area, $context);
    }
}
