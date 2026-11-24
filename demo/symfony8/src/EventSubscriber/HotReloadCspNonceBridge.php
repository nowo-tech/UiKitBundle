<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Dev demo bridge (UiKit 2.72): the Web Profiler adds a script nonce to the response CSP (priority -128), which
 * disables 'unsafe-inline'; HotReloadBundle injects its inline preserve-boot script later (priority -4096) and only
 * takes a nonce from a request attribute. This copies the nonce already in the response's `script-src` into
 * `_hot_reload_csp_nonce` (config `nowo_hot_reload.csp_nonce_request_attribute`), so the dev-only script carries the
 * same nonce and the CSP-asserting e2e pages see no violation. To remove once HotReloadBundle reuses that nonce itself.
 * Stateless (worker safe): reads and writes the current request / response only.
 */
final class HotReloadCspNonceBridge implements EventSubscriberInterface
{
    public const ATTRIBUTE = '_hot_reload_csp_nonce';

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onKernelResponse', -3072]];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $csp = (string) $event->getResponse()->headers->get('Content-Security-Policy', '');
        foreach (explode(';', $csp) as $directive) {
            $parts = preg_split('/\s+/', trim($directive)) ?: [];
            if ('script-src' !== strtolower($parts[0] ?? '')) {
                continue;
            }
            foreach ($parts as $source) {
                if (1 === preg_match("/^'nonce-([A-Za-z0-9+\\/_=-]+)'$/", $source, $m)) {
                    $event->getRequest()->attributes->set(self::ATTRIBUTE, $m[1]);

                    return;
                }
            }
        }
    }
}
