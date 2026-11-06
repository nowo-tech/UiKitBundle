<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Shell;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the app shell's sidebar layout of the current request (2.32, Twig function `nowo_ui_sidebar_layout()`): a
 * valid explicit option, else the request attribute {@see SidebarLayout::ATTRIBUTE} (current request, then the main
 * request), else the configured default. Invalid values are ignored everywhere. It reads the request on every call and
 * keeps nothing, so one instance serves every request of a FrankenPHP worker.
 */
final readonly class SidebarLayoutResolver
{
    /**
     * @param string $default `nowo_ui_kit.shell.sidebar_layout` (anything but `full-height` means `below-header`)
     */
    public function __construct(
        private ?RequestStack $requestStack = null,
        private string $default = 'below-header',
    ) {
    }

    public function resolve(mixed $option = null): string
    {
        $layout = SidebarLayout::fromValue($option);
        if ($layout instanceof SidebarLayout) {
            return $layout->value;
        }
        $current = $this->requestStack?->getCurrentRequest();
        $layout = SidebarLayout::fromValue($current?->attributes->get(SidebarLayout::ATTRIBUTE));
        $main = $this->requestStack?->getMainRequest();
        if (!$layout instanceof SidebarLayout && $main instanceof Request && $main !== $current) {
            $layout = SidebarLayout::fromValue($main->attributes->get(SidebarLayout::ATTRIBUTE));
        }

        return ($layout ?? SidebarLayout::tryFrom($this->default) ?? SidebarLayout::BelowHeader)->value;
    }

    public function isFullHeight(mixed $option = null): bool
    {
        return SidebarLayout::FullHeight->value === $this->resolve($option);
    }
}
