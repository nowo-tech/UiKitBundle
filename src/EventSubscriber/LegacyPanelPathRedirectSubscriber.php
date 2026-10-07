<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\EventSubscriber;

use Nowo\UiKitBundle\Routing\PanelPathRewriter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Permanent redirect from legacy prefixes to the new ones configured in `nowo_ui_kit.panel_path_rewrites`.
 *
 * GET/HEAD requests get a 301; other methods get a 308 so the method and body are preserved.
 */
final class LegacyPanelPathRedirectSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly PanelPathRewriter $rewriter,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Above RouterListener (32) so legacy paths 301 before a 404 from unmatched routes.
        return [KernelEvents::REQUEST => ['onKernelRequest', 64]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $target = $this->rewriter->legacyRedirectTarget($request->getPathInfo());
        if (null === $target) {
            return;
        }

        $url = $request->getBaseUrl().$target;
        $query = $request->getQueryString();
        if (\is_string($query) && '' !== $query) {
            $url .= '?'.$query;
        }

        $status = \in_array($request->getMethod(), [Request::METHOD_GET, Request::METHOD_HEAD], true) ? 301 : 308;

        $event->setResponse(new RedirectResponse($url, $status));
    }
}
