<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Shell;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the page-header presentation of the current request (2.27; Twig global `nowo_ui_header` and the
 * `nowo_ui_header_presentation()` function): a valid explicit option, else the request attribute
 * {@see HeaderPresentation::ATTRIBUTE} (current request, then the main request), else the configured default. Invalid
 * values are ignored everywhere, so a host variable that happens to be called `presentation` never breaks a page. It reads the request
 * on every call and keeps nothing, so one instance serves every request of a FrankenPHP worker.
 */
final readonly class HeaderPresentationResolver
{
    /**
     * @param string $presentation   `nowo_ui_kit.header.presentation` (anything but `compact` means `standard`)
     * @param string $compactActions `nowo_ui_kit.header.compact_actions`: `page` | `bar`
     */
    public function __construct(
        private ?RequestStack $requestStack = null,
        private string $presentation = 'standard',
        private string $compactActions = 'page',
    ) {
    }

    /**
     * The explicit option when it is a valid presentation ({@see HeaderPresentation} or `standard` / `compact`; any other
     * value, e.g. an unrelated host variable named `presentation`, is ignored), else the request attribute (the
     * current request, then the main request, so a page header rendered in a sub-request follows the page), else the
     * configured default.
     */
    public function resolve(mixed $option = null): string
    {
        $fromOption = HeaderPresentation::fromRequestValue($option);
        if ($fromOption instanceof HeaderPresentation) {
            return $fromOption->value;
        }
        $current = $this->requestStack?->getCurrentRequest();
        $fromRequest = HeaderPresentation::fromRequestValue($current?->attributes->get(HeaderPresentation::ATTRIBUTE));
        $main = $this->requestStack?->getMainRequest();
        if (!$fromRequest instanceof HeaderPresentation && $main instanceof Request && $main !== $current) {
            $fromRequest = HeaderPresentation::fromRequestValue($main->attributes->get(HeaderPresentation::ATTRIBUTE));
        }

        return ($fromRequest ?? HeaderPresentation::tryFrom($this->presentation) ?? HeaderPresentation::Standard)->value;
    }

    public function isCompact(mixed $option = null): bool
    {
        return HeaderPresentation::Compact->value === $this->resolve($option);
    }

    /** Where `PageHeader::actions` go in the compact presentation: `page` (default) or `bar`. */
    public function compactActions(): string
    {
        return 'bar' === $this->compactActions ? 'bar' : 'page';
    }
}
