<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * 2.73: both ends of the direct-GET contract of remote dialog content ({@see RemoteDialogContentMap}).
 *
 *  - {@see redirectTarget()}: the owning page URL a top-level GET of a marked route is redirected to, with the reopen
 *    query `_dialog[route]=<dialog route>&_dialog[params][<name>]=<value>…` (the dialog route's parameters and its
 *    scalar query values);
 *  - {@see dialogAttributes()}: on the owning page, the attributes of the remote form dialog: its safe list (the
 *    routes marked for this page and this dialog id) and, when the request carries a reopen query naming one of them,
 *    that route and its URL, generated again by the router from the route name and parameters. The query never
 *    supplies a URL, only a safe-listed route name and parameters, and the result must be a same-origin path
 *    ({@see RemoteDialogTarget}); a foreign or unknown route, or parameters the route rejects, are ignored.
 *
 * Stateless: reads the request it is given, else the current one (FrankenPHP worker safe).
 */
final readonly class RemoteDialogReopen
{
    public const ATTR_PARAM = 'data-nowo-ui-remote-form-reopen-param';

    public const ATTR_ROUTES = 'data-nowo-ui-remote-form-reopen-routes';

    public const ATTR_ROUTE = 'data-nowo-ui-remote-form-reopen-route';

    public const ATTR_URL = 'data-nowo-ui-remote-form-reopen-url';

    /** At most this many reopen parameters are carried or accepted (a bound on crafted queries). */
    private const MAX_PARAMETERS = 32;

    /** At most this many characters per parameter value. */
    private const MAX_VALUE_LENGTH = 512;

    public function __construct(
        private RemoteDialogContentMap $map,
        private ?UrlGeneratorInterface $urls = null,
        private ?RequestStack $requests = null,
    ) {
    }

    public function map(): RemoteDialogContentMap
    {
        return $this->map;
    }

    /**
     * The owning page path (with the reopen query) for a request of a marked route, null when it cannot be generated
     * (no router, a page parameter missing, a parameter the page route rejects, not a same-origin path).
     */
    public function redirectTarget(Request $request, RemoteDialogContent $entry): ?string
    {
        if (!$this->urls instanceof UrlGeneratorInterface) {
            return null;
        }
        $routeParams = $this->scalars($request->attributes->get('_route_params'));
        $pageParams = [];
        foreach ($entry->pageParameters as $name) {
            if (!\array_key_exists($name, $routeParams)) {
                return null;
            }
            $pageParams[$name] = $routeParams[$name];
        }
        $query = $this->scalars($request->query->all());
        unset($query[$this->map->parameter]);
        $params = \array_slice($routeParams + $query, 0, self::MAX_PARAMETERS, true);
        $reopen = ['route' => $entry->route];
        if ([] !== $params) {
            $reopen['params'] = $params;
        }
        try {
            $url = $this->urls->generate($entry->page, $pageParams + [$this->map->parameter => $reopen]);
        } catch (RoutingException) {
            return null;
        }

        return RemoteDialogTarget::tryPath($url, $request);
    }

    /**
     * The reopen attributes of the remote form dialog `$dialog` on the current page: empty when no marked route
     * belongs to this page and dialog (the default markup is unchanged).
     *
     * @return array<string, string>
     */
    public function dialogAttributes(string $dialog, ?Request $request = null): array
    {
        $request ??= $this->requests?->getCurrentRequest();
        $page = $request?->attributes->get('_route');
        if (!$request instanceof Request || !\is_string($page) || $this->map->isEmpty()) {
            return [];
        }
        $routes = $this->map->routesOf($page, $dialog);
        if ([] === $routes) {
            return [];
        }
        $attributes = [self::ATTR_PARAM => $this->map->parameter, self::ATTR_ROUTES => implode(' ', $routes)];
        $target = $this->reopenTarget($request, $routes);
        if (null !== $target) {
            $attributes[self::ATTR_ROUTE] = $target[0];
            $attributes[self::ATTR_URL] = $target[1];
        }

        return $attributes;
    }

    /**
     * The safe-listed route and its regenerated same-origin path named by the request's reopen query, or null.
     *
     * @param list<string> $routes the safe list
     *
     * @return array{0: string, 1: string}|null
     */
    private function reopenTarget(Request $request, array $routes): ?array
    {
        $reopen = $request->query->all()[$this->map->parameter] ?? null;
        if (!\is_array($reopen) || !\is_string($reopen['route'] ?? null) || !\in_array($reopen['route'], $routes, true) || !$this->urls instanceof UrlGeneratorInterface) {
            return null;
        }
        $raw = $reopen['params'] ?? [];
        if (!\is_array($raw) || \count($raw) > self::MAX_PARAMETERS) {
            return null;
        }
        $params = $this->scalars($raw);
        if (\count($params) !== \count($raw)) {
            return null;
        }
        try {
            $url = $this->urls->generate($reopen['route'], $params);
        } catch (RoutingException) {
            return null;
        }
        $path = RemoteDialogTarget::tryPath($url, $request);

        return null === $path ? null : [$reopen['route'], $path];
    }

    /**
     * The string-keyed scalar values of an array (route parameters or a query), as strings; others are dropped.
     *
     * @return array<string, string>
     */
    private function scalars(mixed $values): array
    {
        $scalars = [];
        foreach (\is_array($values) ? $values : [] as $name => $value) {
            if (!\is_string($name) || '' === $name || !\is_scalar($value)) {
                continue;
            }
            $value = \is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            if (\strlen($value) <= self::MAX_VALUE_LENGTH) {
                $scalars[$name] = $value;
            }
        }

        return $scalars;
    }
}
