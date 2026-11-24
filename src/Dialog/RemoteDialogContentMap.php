<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use InvalidArgumentException;

/**
 * 2.73: the routes marked as remote dialog content (`nowo_ui_kit.remote_dialog.direct_get`), keyed by route name, and
 * the name of the reopen query parameter (default `_dialog`). Empty by default: the direct-GET contract is opt-in per
 * route. A kit marks its own routes by prepending `nowo_ui_kit.remote_dialog.direct_get.routes`; the host can add more
 * or override an entry. Immutable, built once from the configuration.
 */
final readonly class RemoteDialogContentMap
{
    public const DEFAULT_PARAMETER = '_dialog';

    /** @var array<string, RemoteDialogContent> */
    private array $routes;

    /**
     * @param array<string, array{page: string, dialog: string, page_parameters?: array<int|string, string>, redirect_same_origin?: bool, page_defaults?: array<string, string|int>, page_resolver?: string|null}|RemoteDialogContent> $routes
     *
     * @throws InvalidArgumentException for an invalid entry or parameter name, or a page parameter named like the
     *                                  reopen parameter
     */
    public function __construct(array $routes = [], public string $parameter = self::DEFAULT_PARAMETER)
    {
        if (!RemoteDialogContent::isParameterName($parameter)) {
            throw new InvalidArgumentException(\sprintf('The remote dialog reopen parameter "%s" must be a query parameter name of letters, digits and "_".', $parameter));
        }
        $entries = [];
        foreach ($routes as $route => $entry) {
            $entries[(string) $route] = $entry instanceof RemoteDialogContent
                ? $entry
                : new RemoteDialogContent((string) $route, $entry['page'], $entry['dialog'], $entry['page_parameters'] ?? [], (bool) ($entry['redirect_same_origin'] ?? false), $entry['page_defaults'] ?? [], $entry['page_resolver'] ?? null);
        }
        $error = self::cycle($entries) ?? self::reopenClash($entries, $parameter);
        if (null !== $error) {
            throw new InvalidArgumentException($error);
        }
        $this->routes = $entries;
    }

    /**
     * The error of an owning page that is itself a marked route (a redirect loop A → B → A, or a chain that drops the
     * first dialog), or null (also used by the configuration).
     *
     * @param array<array-key, RemoteDialogContent|array{page: string, ...}> $routes
     */
    public static function cycle(array $routes): ?string
    {
        foreach ($routes as $route => $entry) {
            $page = $entry instanceof RemoteDialogContent ? $entry->page : $entry['page'];
            if (\array_key_exists($page, $routes)) {
                return \sprintf('The owning page "%s" of remote dialog route "%s" is itself a marked dialog route: a direct visit would redirect again (loop).', $page, (string) $route);
            }
        }

        return null;
    }

    /**
     * 2.74: the error of an entry using the page mapping options whose mapped or default page parameter is reserved
     * ({@see isReservedPageParameter()}: it would replace the reopen query or set the URL fragment), or null (also used
     * by the configuration).
     *
     * @param array<array-key, RemoteDialogContent> $routes
     */
    public static function reopenClash(array $routes, string $parameter): ?string
    {
        foreach ($routes as $route => $entry) {
            if (!$entry->usesPageMapping()) {
                continue;
            }
            foreach ([...array_keys($entry->pageParameterMap()), ...array_keys($entry->pageDefaults)] as $name) {
                if (self::isReservedPageParameter((string) $name, $parameter)) {
                    return \sprintf('The page parameter "%s" of remote dialog route "%s" is reserved (the reopen query parameter "%s" or "_fragment").', (string) $name, (string) $route, $parameter);
                }
            }
        }

        return null;
    }

    /**
     * 2.74: whether a page parameter name is reserved: the reopen query parameter (it would replace the reopen query)
     * or the router's `_fragment`. Used for the configuration and for the keys a page resolver returns (audit ZBD-I1).
     */
    public static function isReservedPageParameter(string $name, string $parameter): bool
    {
        return $name === $parameter || '_fragment' === $name;
    }

    /**
     * 2.74: the service ids of the page resolvers the entries name, sorted, without duplicates.
     *
     * @return list<string>
     */
    public function resolvers(): array
    {
        $ids = [];
        foreach ($this->routes as $entry) {
            if (null !== $entry->pageResolver) {
                $ids[$entry->pageResolver] = true;
            }
        }
        $ids = array_keys($ids);
        sort($ids);

        return $ids;
    }

    /**
     * 2.74: the entries that use the page mapping options, keyed by route (checked against the route collection when
     * the cache is warmed).
     *
     * @return array<string, RemoteDialogContent>
     */
    public function mappedEntries(): array
    {
        return array_filter($this->routes, static fn (RemoteDialogContent $entry): bool => $entry->usesPageMapping());
    }

    public function isEmpty(): bool
    {
        return [] === $this->routes;
    }

    /** The entry of a dialog route, null when the route is not marked. */
    public function get(?string $route): ?RemoteDialogContent
    {
        return null === $route ? null : ($this->routes[$route] ?? null);
    }

    /**
     * The routes a dialog of an owning page may reopen: its safe list, sorted.
     *
     * @return list<string>
     */
    public function routesOf(string $page, string $dialog): array
    {
        $routes = [];
        foreach ($this->routes as $route => $entry) {
            if ($entry->page === $page && $entry->dialog === $dialog) {
                $routes[] = $route;
            }
        }
        sort($routes);

        return $routes;
    }
}
