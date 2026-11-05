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
 */
final readonly class RemoteDialogResponder
{
    public function __construct(
        private ?RequestStack $requests = null,
        private ?UrlGeneratorInterface $urls = null,
    ) {
    }

    /** Whether the request (default: the current one) is a dialog request. */
    public function isDialog(?Request $request = null): bool
    {
        return RemoteDialog::isRequest($request ?? $this->requests?->getCurrentRequest());
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
        if (RemoteDialog::isRequest($request)) {
            return self::dialogRedirect($path);
        }

        return RemoteDialog::vary(new RedirectResponse($path, $status));
    }

    /**
     * {@see redirect()} to a route.
     *
     * @param array<string, mixed> $parameters
     */
    public function redirectToRoute(string $route, array $parameters = [], int $status = Response::HTTP_SEE_OTHER, ?Request $request = null): Response
    {
        if (!$this->urls instanceof UrlGeneratorInterface) {
            throw new LogicException('RemoteDialogResponder::redirectToRoute() needs the router (symfony/routing).');
        }

        return $this->redirect($this->urls->generate($route, $parameters), $status, $request);
    }

    /** Marks a page / fragment answer of a two-mode URL: adds `Vary: X-Nowo-Dialog` (KIT-68). */
    public function fragment(Response $response): Response
    {
        return RemoteDialog::vary($response);
    }

    /** The fragment template for a dialog request, else the page template. */
    public function template(string $page, string $fragment, ?Request $request = null): string
    {
        return $this->isDialog($request) ? $fragment : $page;
    }

    /** `204` + `X-Nowo-Redirect` + `Vary` + `no-store` for an already validated path. */
    public static function dialogRedirect(string $path): Response
    {
        $response = new Response(null, Response::HTTP_NO_CONTENT, [RemoteDialog::REDIRECT_HEADER => $path]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return RemoteDialog::vary($response);
    }
}
