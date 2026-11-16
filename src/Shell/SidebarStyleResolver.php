<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Shell;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the app shell's sidebar style of the current request (2.52, Twig function `nowo_ui_sidebar_style()`): a
 * valid explicit option, else the request attribute {@see SidebarStyle::ATTRIBUTE} (current request, then the main
 * request), else the configured default. Invalid values are ignored everywhere. It reads the request on every call and
 * keeps nothing, so one instance serves every request of a FrankenPHP worker (kernel reset false).
 */
final readonly class SidebarStyleResolver
{
    /**
     * @param string $default `nowo_ui_kit.shell.sidebar_style` (anything but `inset` means `classic`)
     */
    public function __construct(
        private ?RequestStack $requestStack = null,
        private string $default = 'classic',
    ) {
    }

    public function resolve(mixed $option = null): string
    {
        $style = SidebarStyle::fromValue($option);
        if ($style instanceof SidebarStyle) {
            return $style->value;
        }
        $current = $this->requestStack?->getCurrentRequest();
        $style = SidebarStyle::fromValue($current?->attributes->get(SidebarStyle::ATTRIBUTE));
        $main = $this->requestStack?->getMainRequest();
        if (!$style instanceof SidebarStyle && $main instanceof Request && $main !== $current) {
            $style = SidebarStyle::fromValue($main->attributes->get(SidebarStyle::ATTRIBUTE));
        }

        return ($style ?? SidebarStyle::tryFrom($this->default) ?? SidebarStyle::Classic)->value;
    }

    public function isInset(mixed $option = null): bool
    {
        return SidebarStyle::Inset->value === $this->resolve($option);
    }
}
