<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Server side of the remote form dialog (2.28): the success answer and the template choice for a URL that serves both
 * the full page and the dialog fragment. Stateless (reads the request it is given, else the current one).
 *
 *     return $this->dialog->redirectToRoute('project_settings', ['id' => $project->getId()]);
 *     return $this->dialog->stepToRoute('connection_containers', ['id' => $id]); // 2.33: next step, same dialog
 *
 * Header names: `nowo_ui_kit.remote_dialog.*_header` (2.33, default `X-Nowo-*`).
 */
final readonly class RemoteDialogResponder
{
    private RemoteDialogHeaders $headers;

    public function __construct(
        private ?RequestStack $requests = null,
        private ?UrlGeneratorInterface $urls = null,
        ?RemoteDialogHeaders $headers = null,
    ) {
        $this->headers = $headers ?? RemoteDialogHeaders::defaults();
    }

    /** The header names in use (2.33). */
    public function headers(): RemoteDialogHeaders
    {
        return $this->headers;
    }

    /** Whether the request (default: the current one) is a dialog request. */
    public function isDialog(?Request $request = null): bool
    {
        return RemoteDialog::isRequest($request ?? $this->requests?->getCurrentRequest(), $this->headers);
    }

    /**
     * For a dialog request: `204` + `X-Nowo-Redirect: <path>` + `Vary` + `Cache-Control: no-store`. Otherwise a
     * `RedirectResponse` to the same path. The URL must be a same-origin path or an absolute URL on the request's own
     * origin (reduced to its path); anything else throws.
     */
    public function redirect(string $url, int $status = Response::HTTP_SEE_OTHER, ?Request $request = null): Response
    {
        $request ??= $this->requests?->getCurrentRequest();
        $path = RemoteDialogTarget::path($url, $request);
        if (RemoteDialog::isRequest($request, $this->headers)) {
            return self::dialogRedirect($path, $this->headers);
        }

        return RemoteDialog::vary(new RedirectResponse($path, $status), $this->headers);
    }

    /**
     * {@see redirect()} to a route.
     *
     * @param array<string, mixed> $parameters
     */
    public function redirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_SEE_OTHER, ?Request $request = null): Response
    {
        return $this->redirect($this->generate($route, $parameters), $status, $request);
    }

    /**
     * 2.33, the next step of a multi-step flow: for a dialog request `204` + `X-Nowo-Dialog-Location: <path>` + `Vary`
     * + `no-store` (the dialog stays open and loads that path in place); otherwise a `RedirectResponse` to the same
     * path (the full-page flow is unchanged). Same URL rule as {@see redirect()}: a same-origin path, else it throws.
     */
    public function step(string $url, int $status = Response::HTTP_SEE_OTHER, ?Request $request = null): Response
    {
        $request ??= $this->requests?->getCurrentRequest();
        $path = RemoteDialogTarget::path($url, $request);
        if (RemoteDialog::isRequest($request, $this->headers)) {
            return self::dialogStep($path, $this->headers);
        }

        return RemoteDialog::vary(new RedirectResponse($path, $status), $this->headers);
    }

    /**
     * {@see step()} to a route.
     *
     * @param array<string, mixed> $parameters
     */
    public function stepToRoute(string $route, array $parameters = [], int $status = Response::HTTP_SEE_OTHER, ?Request $request = null): Response
    {
        return $this->step($this->generate($route, $parameters), $status, $request);
    }

    /** Marks a page / fragment answer of a two-mode URL: adds `Vary: X-Nowo-Dialog` (KIT-68). */
    public function fragment(Response $response): Response
    {
        return RemoteDialog::vary($response, $this->headers);
    }

    /** The fragment template for a dialog request, else the page template. */
    public function template(string $page, string $fragment, ?Request $request = null): string
    {
        return $this->isDialog($request) ? $fragment : $page;
    }

    /** `204` + `X-Nowo-Redirect` + `Vary` + `no-store` for an already validated path. */
    public static function dialogRedirect(string $path, ?RemoteDialogHeaders $headers = null): Response
    {
        return self::noContent($headers->redirect ?? RemoteDialog::REDIRECT_HEADER, $path, $headers);
    }

    /** 2.33: `204` + `X-Nowo-Dialog-Location` + `Vary` + `no-store` for an already validated path. */
    public static function dialogStep(string $path, ?RemoteDialogHeaders $headers = null): Response
    {
        return self::noContent($headers->location ?? RemoteDialog::LOCATION_HEADER, $path, $headers);
    }

    private static function noContent(string $header, string $path, ?RemoteDialogHeaders $headers): Response
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT, [$header => $path]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return RemoteDialog::vary($response, $headers);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function generate(string $route, array $parameters): string
    {
        if (!$this->urls instanceof UrlGeneratorInterface) {
            throw new LogicException('RemoteDialogResponder::redirectToRoute() / stepToRoute() need the router (symfony/routing).');
        }

        return $this->urls->generate($route, $parameters);
    }
}
