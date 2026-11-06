<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Opt-in (`nowo_ui_kit.remote_dialog.convert_redirects: true`, 2.28): a `RedirectResponse` answered to a main dialog
 * request becomes `204` + `X-Nowo-Redirect` when its target is a same-origin path, so `redirectToRoute()` controllers
 * work in the remote form dialog unchanged (and the flash survives: the dialog never follows the 3xx itself). Cookies
 * and the headers set by earlier listeners (`X-Frame-Options`, CSP, HSTS…) are kept on the 204; `Location`, the body
 * headers and the caching headers are the 204's own. Any other response is left as is. Stateless.
 *
 * 2.33: routes matching `remote_dialog.step_routes` (route names, `*` = any characters) answer their redirects as a
 * step instead (`204` + `X-Nowo-Dialog-Location`: the dialog loads the next step in place), and every answer of those
 * routes varies on the request header. A {@see RemoteDialogRedirectEvent} listener can change the answer or its path
 * per redirect. The header names are `remote_dialog.*_header`.
 */
final readonly class RemoteDialogRedirectListener
{
    /** Headers of the redirect that the 204 replaces with its own (lower case), besides the three dialog headers. */
    private const OWN_HEADERS = ['location', 'content-type', 'content-length', 'content-encoding', 'cache-control', 'expires', 'pragma', 'vary', 'date'];

    private RemoteDialogHeaders $headers;

    /** @var list<string> */
    private array $ownHeaders;

    private ?string $stepRoutes;

    /**
     * @param list<string> $stepRoutes route names whose redirects become step answers (`*` = any characters)
     */
    public function __construct(
        ?RemoteDialogHeaders $headers = null,
        private bool $convertRedirects = true,
        array $stepRoutes = [],
        private ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->headers = $headers ?? RemoteDialogHeaders::defaults();
        $this->ownHeaders = [...self::OWN_HEADERS, ...array_map(strtolower(...), [$this->headers->redirect, $this->headers->location, RemoteDialog::REDIRECT_HEADER, RemoteDialog::LOCATION_HEADER])];
        $this->stepRoutes = self::routePattern($stepRoutes);
    }

    /**
     * The regular expression of a list of route name patterns (`*` = any characters), null for none.
     *
     * @param array<mixed> $patterns
     */
    public static function routePattern(array $patterns): ?string
    {
        $parts = [];
        foreach ($patterns as $pattern) {
            if (\is_string($pattern) && '' !== $pattern) {
                $parts[] = str_replace('\*', '.*', preg_quote($pattern, '/'));
            }
        }

        return [] === $parts ? null : '/^(?:'.implode('|', $parts).')$/D';
    }

    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $response = $event->getResponse();
        $route = $request->attributes->get('_route');
        $stepRoute = null !== $this->stepRoutes && \is_string($route) && 1 === preg_match($this->stepRoutes, $route);
        if ($stepRoute) {
            // A step route serves the page and its fragment on one URL: every answer varies on the dialog header.
            RemoteDialog::vary($response, $this->headers);
        }
        if (!$response instanceof RedirectResponse || !RemoteDialog::isRequest($request, $this->headers)) {
            return;
        }
        $path = RemoteDialogTarget::tryPath($response->getTargetUrl(), $request);
        if (null === $path) {
            return;
        }
        $answer = $stepRoute ? RemoteDialogRedirectEvent::ANSWER_STEP : ($this->convertRedirects ? RemoteDialogRedirectEvent::ANSWER_REDIRECT : null);
        if ($this->dispatcher instanceof EventDispatcherInterface) {
            $decision = new RemoteDialogRedirectEvent($request, $response, $path, $answer);
            $this->dispatcher->dispatch($decision);
            $answer = $decision->getAnswer();
            $path = $decision->getPath();
        }
        if (null === $answer) {
            return;
        }
        $event->setResponse($this->convert($response, RemoteDialogRedirectEvent::ANSWER_STEP === $answer
            ? RemoteDialogResponder::dialogStep($path, $this->headers)
            : RemoteDialogResponder::dialogRedirect($path, $this->headers)));
    }

    /** The 204 with the redirect's cookies and its other headers. */
    private function convert(RedirectResponse $response, Response $converted): Response
    {
        foreach ($response->headers->allPreserveCaseWithoutCookies() as $name => $values) {
            if (\in_array(strtolower($name), $this->ownHeaders, true)) {
                continue;
            }
            $converted->headers->set($name, $values);
        }
        foreach ($response->headers->getCookies() as $cookie) {
            $converted->headers->setCookie($cookie);
        }

        return $converted;
    }
}
