<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Shell;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Resolves the page-header presentation of the current request (2.27; Twig global `nowo_ui_header` and the
 * `nowo_ui_header_presentation()` function): the explicit option (an invalid one throws), else the request attribute
 * {@see HeaderPresentation::ATTRIBUTE} (an invalid one is ignored), else the configured default. It reads the request
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

    public function resolve(HeaderPresentation|string|null $option = null): string
    {
        if (null !== $option) {
            return HeaderPresentation::fromOption($option)->value;
        }
        $fromRequest = HeaderPresentation::fromRequestValue($this->requestStack?->getCurrentRequest()?->attributes->get(HeaderPresentation::ATTRIBUTE));

        return ($fromRequest ?? HeaderPresentation::tryFrom($this->presentation) ?? HeaderPresentation::Standard)->value;
    }

    public function isCompact(HeaderPresentation|string|null $option = null): bool
    {
        return HeaderPresentation::Compact->value === $this->resolve($option);
    }

    /** Where `PageHeader::actions` go in the compact presentation: `page` (default) or `bar`. */
    public function compactActions(): string
    {
        return 'bar' === $this->compactActions ? 'bar' : 'page';
    }
}
