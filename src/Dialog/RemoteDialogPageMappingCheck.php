<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use LogicException;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * 2.74, non-optional cache warmer (registered only when a `remote_dialog.direct_get` entry uses `page_parameters` as a
 * map, `page_defaults` or `page_resolver`): checks those entries against the route collection when the container is
 * built (the kernel runs non-optional warmers right after it dumps a new container) and on `cache:warmup`. An error
 * stops the boot with a message naming the entry, so a wrong mapping never ships as a silent "no redirect":
 *
 *  - the dialog route and the owning page route exist (a localized route counts through its `.<locale>` variants);
 *  - every mapped page parameter and every `page_defaults` key is a variable of the page route;
 *  - every `page_defaults` value matches the page route's requirement for it;
 *  - every mapped dialog parameter is a variable or a default of the dialog route;
 *  - without a `page_resolver`, every required page route variable (no default) is mapped or has a page default.
 *
 * 2.73 entries (a list of names, no defaults, no resolver) are not checked, so their behaviour is unchanged.
 * Stateless: reads the router when it runs and keeps nothing.
 *
 * @internal wiring of `remote_dialog.direct_get`; hosts and kits configure it, they do not call it (no public port: REQ-ARCH-001 rule 3)
 */
final readonly class RemoteDialogPageMappingCheck implements CacheWarmerInterface
{
    public function __construct(
        private RemoteDialogContentMap $map,
        private RouterInterface $router,
    ) {
    }

    public function isOptional(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     *
     * @throws LogicException when an entry does not match its routes
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $entries = $this->map->mappedEntries();
        if ([] === $entries) {
            return [];
        }
        $routes = $this->router->getRouteCollection();
        foreach ($entries as $entry) {
            $error = self::check($entry, $routes);
            if (null !== $error) {
                throw new LogicException('Invalid nowo_ui_kit.remote_dialog.direct_get.routes: '.$error);
            }
        }

        return [];
    }

    /** The error of an entry against the route collection, or null. */
    public static function check(RemoteDialogContent $entry, RouteCollection $routes): ?string
    {
        $dialogRoutes = self::variants($routes, $entry->route);
        if ([] === $dialogRoutes) {
            return \sprintf('the remote dialog route "%s" is not a route of the application.', $entry->route);
        }
        $pageRoutes = self::variants($routes, $entry->page);
        if ([] === $pageRoutes) {
            return \sprintf('the owning page "%s" of remote dialog route "%s" is not a route of the application.', $entry->page, $entry->route);
        }
        $map = $entry->pageParameterMap();
        foreach ($dialogRoutes as $dialogRoute) {
            $known = array_merge($dialogRoute->compile()->getVariables(), array_keys($dialogRoute->getDefaults()));
            foreach ($map as $name) {
                if (!\in_array($name, $known, true)) {
                    return \sprintf('the page_parameters of remote dialog route "%s" read "%s", which is not a parameter of that route.', $entry->route, $name);
                }
            }
        }
        foreach ($pageRoutes as $pageRoute) {
            $variables = $pageRoute->compile()->getVariables();
            foreach (array_merge(array_keys($map), array_keys($entry->pageDefaults)) as $name) {
                if (!\in_array($name, $variables, true)) {
                    return \sprintf('the page parameter "%s" of remote dialog route "%s" is not a parameter of the owning page "%s".', $name, $entry->route, $entry->page);
                }
            }
            foreach ($entry->pageDefaults as $name => $value) {
                $requirement = $pageRoute->getRequirement($name);
                if (null !== $requirement && 1 !== preg_match('#^(?:'.$requirement.')$#Du', (string) $value)) {
                    return \sprintf('the page_defaults value of "%s" of remote dialog route "%s" does not match the requirement of the owning page "%s".', $name, $entry->route, $entry->page);
                }
            }
            if (null !== $entry->pageResolver) {
                continue;
            }
            foreach ($variables as $name) {
                if (!$pageRoute->hasDefault($name) && !\array_key_exists($name, $map) && !\array_key_exists($name, $entry->pageDefaults)) {
                    return \sprintf('the owning page "%s" of remote dialog route "%s" requires the parameter "%s": map it in page_parameters, set it in page_defaults or use a page_resolver.', $entry->page, $entry->route, $name);
                }
            }
        }

        return null;
    }

    /**
     * The route of that name, or the `.<locale>` variants of a localized route.
     *
     * @return list<Route>
     */
    private static function variants(RouteCollection $routes, string $name): array
    {
        $route = $routes->get($name);
        if ($route instanceof Route) {
            return [$route];
        }
        $variants = [];
        foreach ($routes->all() as $candidate) {
            if ($name === $candidate->getDefault('_canonical_route')) {
                $variants[] = $candidate;
            }
        }

        return $variants;
    }
}
