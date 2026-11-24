<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use InvalidArgumentException;

/**
 * 2.73: one route marked as remote dialog content (`nowo_ui_kit.remote_dialog.direct_get.routes.<route>`): the route
 * whose answer is loaded into a remote form dialog, the owning page that holds that dialog, the dialog's element id,
 * and the route parameters the owning page needs (copied by name from the dialog route). Immutable.
 *
 * 2.74: the page parameters can also be a map `{pageParam: dialogParam}` (a list copies by identical name, as in
 * 2.73), `pageDefaults` adds fixed page route parameters (`{section: access}`), and `pageResolver` names a
 * {@see RemoteDialogPageResolverInterface} service for page parameters that come from the record.
 *
 * A top-level navigation to the route (address bar, bookmark, a link opened in a new tab) is redirected to the owning
 * page with a reopen query, and the owning page opens the dialog on that route ({@see RemoteDialogDirectGetListener},
 * {@see RemoteDialogReopen}).
 */
final readonly class RemoteDialogContent
{
    private const ROUTE = '/^[A-Za-z0-9_.-]{1,200}$/D';

    private const DIALOG_ID = '/^[A-Za-z][A-Za-z0-9_:.-]{0,127}$/D';

    private const PARAMETER = '/^[A-Za-z_][A-Za-z0-9_]{0,63}$/D';

    private const SERVICE_ID = '/^[A-Za-z_\\\\][A-Za-z0-9_.\\\\-]{0,254}$/D';

    /** At most this many characters per `page_defaults` value (same bound as a carried parameter value). */
    private const MAX_DEFAULT_LENGTH = 512;

    /**
     * @param array<int|string, string> $pageParameters     route parameters of the dialog route the owning page route is
     *                                                      generated with: a list (copied by identical name) or, 2.74,
     *                                                      a map `{pageParam: dialogParam}` (a list item counts as
     *                                                      `{name: name}`)
     * @param bool                      $redirectSameOrigin also redirect a navigation from a page of this origin
     *                                                      (`Sec-Fetch-Site: same-origin`: a link followed without
     *                                                      JavaScript, or opened in a new tab); false (default) keeps
     *                                                      the route's own page for them, the no-JS fallback
     * @param array<string, string|int> $pageDefaults       2.74: fixed page route parameters (`{section: access}`)
     * @param string|null               $pageResolver       2.74: id of a {@see RemoteDialogPageResolverInterface} service
     *
     * @throws InvalidArgumentException when a name is not a route name, a dialog id, a parameter name or a service id
     */
    public function __construct(
        public string $route,
        public string $page,
        public string $dialog,
        public array $pageParameters = [],
        public bool $redirectSameOrigin = false,
        public array $pageDefaults = [],
        public ?string $pageResolver = null,
    ) {
        $error = self::invalid($route, $page, $dialog, $pageParameters, $pageDefaults, $pageResolver);
        if (null !== $error) {
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * The validation error of an entry, or null when it is valid (also used by the configuration).
     *
     * @param array<mixed> $pageParameters
     * @param array<mixed> $pageDefaults
     */
    public static function invalid(string $route, string $page, string $dialog, array $pageParameters, array $pageDefaults = [], ?string $pageResolver = null): ?string
    {
        foreach (['dialog route' => $route, 'page route' => $page] as $role => $name) {
            if (1 !== preg_match(self::ROUTE, $name)) {
                return \sprintf('The remote dialog %s "%s" must be a route name of letters, digits, "_", "." and "-".', $role, $name);
            }
        }
        if ($route === $page) {
            return \sprintf('The remote dialog route "%s" cannot be its own owning page.', $route);
        }
        if (1 !== preg_match(self::DIALOG_ID, $dialog)) {
            return \sprintf('The remote dialog id "%s" of route "%s" must be an element id starting with a letter (letters, digits, "_", ":", ".", "-").', $dialog, $route);
        }
        foreach ($pageParameters as $pageName => $name) {
            if (!\is_string($name) || 1 !== preg_match(self::PARAMETER, $name)) {
                return \sprintf('The page_parameters of remote dialog route "%s" must be route parameter names.', $route);
            }
            if (\is_string($pageName) && 1 !== preg_match(self::PARAMETER, $pageName)) {
                return \sprintf('The page_parameters of remote dialog route "%s" must map page route parameter names to dialog route parameter names, "%s" given.', $route, $pageName);
            }
        }
        $mapped = self::mapOf($pageParameters);
        foreach ($pageDefaults as $name => $value) {
            if (!\is_string($name) || 1 !== preg_match(self::PARAMETER, $name)) {
                return \sprintf('The page_defaults of remote dialog route "%s" must be keyed by page route parameter names.', $route);
            }
            if (!\is_string($value) && !\is_int($value) || \strlen((string) $value) > self::MAX_DEFAULT_LENGTH) {
                return \sprintf('The page_defaults value "%s" of remote dialog route "%s" must be a string or an integer of at most %d characters.', $name, $route, self::MAX_DEFAULT_LENGTH);
            }
            if (\array_key_exists($name, $mapped)) {
                return \sprintf('The page parameter "%s" of remote dialog route "%s" is both in page_parameters and in page_defaults.', $name, $route);
            }
        }
        if (null !== $pageResolver && 1 !== preg_match(self::SERVICE_ID, $pageResolver)) {
            return \sprintf('The page_resolver of remote dialog route "%s" must be a service id, "%s" given.', $route, $pageResolver);
        }

        return null;
    }

    /**
     * The page parameters as a map page route parameter => dialog route parameter (a list item maps to itself), in
     * the configured order.
     *
     * @return array<string, string>
     */
    public function pageParameterMap(): array
    {
        return self::mapOf($this->pageParameters);
    }

    /**
     * 2.74: whether the entry uses the page mapping options (a `page_parameters` map, `page_defaults` or a
     * `page_resolver`); only those entries are checked against the route collection when the cache is warmed, so a
     * 2.73 entry behaves exactly as before.
     */
    public function usesPageMapping(): bool
    {
        return !array_is_list($this->pageParameters) || [] !== $this->pageDefaults || null !== $this->pageResolver;
    }

    /**
     * @param array<mixed> $pageParameters
     *
     * @return array<string, string>
     */
    private static function mapOf(array $pageParameters): array
    {
        $map = [];
        foreach ($pageParameters as $pageName => $name) {
            if (\is_string($name)) {
                $map[\is_string($pageName) ? $pageName : $name] = $name;
            }
        }

        return $map;
    }

    /** Whether a reopen query parameter name is valid (`_dialog` by default). */
    public static function isParameterName(string $name): bool
    {
        return 1 === preg_match(self::PARAMETER, $name);
    }
}
