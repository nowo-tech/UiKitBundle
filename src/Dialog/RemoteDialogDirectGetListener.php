<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * 2.73, `kernel.response` (registered only when `nowo_ui_kit.remote_dialog.direct_get.routes` is not empty): a
 * top-level navigation to a route marked as remote dialog content lands on its owning page with the dialog open on
 * that route, instead of a bare fragment page.
 *
 * It converts only a successful answer: a main `GET` / `HEAD` request of a marked route, answered `200` with an HTML
 * (or untyped) body, that is a top-level navigation ({@see isTopLevelNavigation()}) not started from a page of this
 * origin (`Sec-Fetch-Site: same-origin` keeps the route's own page, the no-JavaScript fallback, unless the route sets
 * `redirect_same_origin`): the address bar, a bookmark, a link from another site or an e-mail. Running on the response means the
 * security firewall and the controller's own access check have already run: a denied request is a 401 / 403 / 404 or
 * a login redirect, never a 200, so it is never redirected (no route or resource oracle). POSTs, 422 re-renders, the
 * dialog's own fetches and unmarked routes are left as they are. The redirect is a `302` to the owning page with the
 * reopen query ({@see RemoteDialogReopen::redirectTarget()}); the cookies of the converted answer are kept. Every
 * answer of a marked route varies on the dialog request header and `Sec-Fetch-Mode` / `-Dest` / `-Site`. Stateless.
 *
 * @internal wiring of `remote_dialog.direct_get`; hosts and kits configure it, they do not call it (no public port: REQ-ARCH-001 rule 3)
 */
final readonly class RemoteDialogDirectGetListener
{
    private RemoteDialogHeaders $headers;

    public function __construct(
        private RemoteDialogReopen $reopen,
        ?RemoteDialogHeaders $headers = null,
    ) {
        $this->headers = $headers ?? RemoteDialogHeaders::defaults();
    }

    /**
     * Whether the request is a top-level document navigation: not a remote dialog fetch (`X-Nowo-Dialog: 1`, name
     * configurable), not an XHR / Turbo frame request, and the browser says so with `Sec-Fetch-Mode: navigate` (and
     * `Sec-Fetch-Dest: document` when it sends a destination). Without a `Sec-Fetch-Mode` header (an old browser, a
     * plain-HTTP origin that is not localhost, a script) nothing proves a navigation: the answer is left as is.
     */
    public static function isTopLevelNavigation(Request $request, ?RemoteDialogHeaders $headers = null): bool
    {
        if (RemoteDialog::isRequest($request, $headers) || $request->isXmlHttpRequest() || $request->headers->has('Turbo-Frame')) {
            return false;
        }
        $dest = $request->headers->get('Sec-Fetch-Dest');

        return 'navigate' === strtolower((string) $request->headers->get('Sec-Fetch-Mode'))
            && (null === $dest || 'document' === strtolower($dest));
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $entry = $this->reopen->map()->get(\is_string($route) ? $route : null);
        if (!$entry instanceof RemoteDialogContent) {
            return;
        }
        $response = $event->getResponse();
        $this->vary($response);
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            return;
        }
        if (Response::HTTP_OK !== $response->getStatusCode() || !$this->isHtml($response) || !self::isTopLevelNavigation($request, $this->headers)) {
            return;
        }
        // Audit ZBD-L1: a streamed answer may check access only inside its callback, which has not run yet: no page
        // resolver for it (a 2.73 entry without resolver is unchanged).
        if (null !== $entry->pageResolver && $response instanceof StreamedResponse) {
            return;
        }
        // A link of this origin followed as a page (no JavaScript, or a new tab) keeps the route's own page.
        if (!$entry->redirectSameOrigin && 'same-origin' === strtolower((string) $request->headers->get('Sec-Fetch-Site'))) {
            return;
        }
        $target = $this->reopen->redirectTarget($request, $entry);
        if (null === $target) {
            return;
        }
        $redirect = new RedirectResponse($target, Response::HTTP_FOUND);
        $redirect->headers->set('Cache-Control', 'no-store, private');
        foreach ($response->headers->getCookies() as $cookie) {
            $redirect->headers->setCookie($cookie);
        }
        $this->vary($redirect);
        $event->setResponse($redirect);
    }

    private function vary(Response $response): void
    {
        RemoteDialog::vary($response, $this->headers);
        $response->setVary(['Sec-Fetch-Mode', 'Sec-Fetch-Dest', 'Sec-Fetch-Site'], false);
    }

    private function isHtml(Response $response): bool
    {
        $type = $response->headers->get('Content-Type');

        return null === $type || str_starts_with(strtolower(trim($type)), 'text/html');
    }
}
