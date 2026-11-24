<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use InvalidArgumentException;

/**
 * 2.73: one route marked as remote dialog content (`nowo_ui_kit.remote_dialog.direct_get.routes.<route>`): the route
 * whose answer is loaded into a remote form dialog, the owning page that holds that dialog, the dialog's element id,
 * and the route parameters the owning page needs (copied by name from the dialog route). Immutable.
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

    /**
     * @param list<string> $pageParameters     route parameters of the dialog route the owning page route is generated with
     * @param bool         $redirectSameOrigin also redirect a navigation from a page of this origin (`Sec-Fetch-Site:
     *                                         same-origin`: a link followed without JavaScript, or opened in a new tab);
     *                                         false (default) keeps the route's own page for them, the no-JS fallback
     *
     * @throws InvalidArgumentException when a name is not a route name, a dialog id or a parameter name
     */
    public function __construct(
        public string $route,
        public string $page,
        public string $dialog,
        public array $pageParameters = [],
        public bool $redirectSameOrigin = false,
    ) {
        $error = self::invalid($route, $page, $dialog, $pageParameters);
        if (null !== $error) {
            throw new InvalidArgumentException($error);
        }
    }

    /**
     * The validation error of an entry, or null when it is valid (also used by the configuration).
     *
     * @param array<mixed> $pageParameters
     */
    public static function invalid(string $route, string $page, string $dialog, array $pageParameters): ?string
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
        foreach ($pageParameters as $name) {
            if (!\is_string($name) || 1 !== preg_match(self::PARAMETER, $name)) {
                return \sprintf('The page_parameters of remote dialog route "%s" must be route parameter names.', $route);
            }
        }

        return null;
    }

    /** Whether a reopen query parameter name is valid (`_dialog` by default). */
    public static function isParameterName(string $name): bool
    {
        return 1 === preg_match(self::PARAMETER, $name);
    }
}
