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
     * @param array<string, array{page: string, dialog: string, page_parameters?: list<string>, redirect_same_origin?: bool}|RemoteDialogContent> $routes
     *
     * @throws InvalidArgumentException for an invalid entry or parameter name
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
                : new RemoteDialogContent((string) $route, $entry['page'], $entry['dialog'], $entry['page_parameters'] ?? [], (bool) ($entry['redirect_same_origin'] ?? false));
        }
        $error = self::cycle($entries);
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
