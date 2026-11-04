<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Opt-in (`nowo_ui_kit.remote_dialog.convert_redirects: true`, 2.28): a `RedirectResponse` answered to a main dialog
 * request becomes `204` + `X-Nowo-Redirect` when its target is a same-origin path, so `redirectToRoute()` controllers
 * work in the remote form dialog unchanged (and the flash survives: the dialog never follows the 3xx itself). Any
 * other response is left as is. Stateless.
 */
final readonly class RemoteDialogRedirectListener
{
    public function __invoke(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        $response = $event->getResponse();
        if (!$event->isMainRequest() || !$response instanceof RedirectResponse || !RemoteDialog::isRequest($request)) {
            return;
        }
        $path = RemoteDialogTarget::tryPath($response->getTargetUrl(), $request);
        if (null === $path) {
            return;
        }
        $converted = RemoteDialogResponder::dialogRedirect($path);
        foreach ($response->headers->getCookies() as $cookie) {
            $converted->headers->setCookie($cookie);
        }
        $event->setResponse($converted);
    }
}
