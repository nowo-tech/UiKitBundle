<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Routing;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\RouteCollection;

/**
 * Decorates the framework `routing.loader` so route paths follow `nowo_ui_kit.panel_path_rewrites`.
 *
 * Only registered when the map is non-empty.
 */
final class PanelPathRewritingLoader implements LoaderInterface
{
    public function __construct(
        private readonly LoaderInterface $inner,
        private readonly PanelPathRewriter $rewriter,
    ) {
    }

    public function load(mixed $resource, ?string $type = null): mixed
    {
        $routes = $this->inner->load($resource, $type);
        if ($routes instanceof RouteCollection) {
            $this->rewriter->rewriteCollection($routes);
        }

        return $routes;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $this->inner->supports($resource, $type);
    }

    public function getResolver(): LoaderResolverInterface
    {
        return $this->inner->getResolver();
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->inner->setResolver($resolver);
    }
}
