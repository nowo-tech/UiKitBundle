<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Release;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET JSON endpoint consumed by `_release_version.html.twig` (IIFE `nowo-ui-release.js` or Stimulus peer `release-status`).
 *
 * Only exposes the configured version label + public GitHub release metadata.
 * Returns 404 when `nowo_ui_kit.release_check.enabled` is false (route imported but feature off).
 *
 * Opt-in route (REQ-SF-004): loaded only through `@NowoUiKitBundle/Resources/config/routes/release_check.yaml`
 * (a `type: attribute` import of this class); the service is registered explicitly, never autoconfigured.
 */
#[Route(path: '/_nowo-ui/release/status', name: 'nowo_ui_kit_release_status', methods: ['GET'])]
final readonly class ReleaseStatusController
{
    public function __construct(
        private ?ReleaseUpdateChecker $checker = null,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        if (!$this->checker instanceof ReleaseUpdateChecker || !$this->checker->isEnabled()) {
            return new JsonResponse(['status' => ReleaseUpdateInfo::STATUS_SKIPPED], Response::HTTP_NOT_FOUND, [
                'Cache-Control' => 'no-store',
            ]);
        }

        $response = new JsonResponse($this->checker->check()->toArray());
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store');

        return $response;
    }
}
