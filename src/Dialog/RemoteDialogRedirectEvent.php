<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use InvalidArgumentException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * 2.33 hook of the redirect listener (`nowo_ui_kit.remote_dialog`): dispatched for every `RedirectResponse` answered to
 * a main dialog request whose target is a same-origin path, before it is converted. A listener reads the request, the
 * redirect and the proposed answer, and may change it:
 *
 *  - {@see ANSWER_REDIRECT}: `204` + `X-Nowo-Redirect` (the dialog closes and the page navigates there);
 *  - {@see ANSWER_STEP}: `204` + `X-Nowo-Dialog-Location` (the dialog stays open and loads the path, next step);
 *  - null: the redirect is left as is.
 *
 * The proposed answer is `step` for a route matching `remote_dialog.step_routes`, else `redirect` with
 * `convert_redirects: true`, else null. {@see setPath()} retargets the answer; the path is validated again (same-origin
 * only), so a listener cannot open a redirect to another origin.
 *
 * 2.49 (REQ-ARCH-001 rule 3): the request-free accessors {@see getRequestPath()}, {@see getRequestRoute()}, {@see getRouteParameters()},
 * {@see getRedirectTargetUrl()} and {@see getRedirectStatusCode()} give a listener what it needs as scalars;
 * {@see getRequest()} / {@see getResponse()} are deprecated (removed in 3.0).
 */
final class RemoteDialogRedirectEvent extends Event
{
    public const ANSWER_REDIRECT = 'redirect';

    public const ANSWER_STEP = 'step';

    /**
     * @internal built by the kit's redirect listener; listeners only read and change it
     */
    public function __construct(
        private readonly Request $request,
        private readonly RedirectResponse $response,
        private string $path,
        private ?string $answer,
    ) {
    }

    /**
     * @deprecated since 2.49, use {@see getRequestPath()} / {@see getRequestRoute()} / {@see getRouteParameters()}; removed in 3.0
     */
    public function getRequest(): Request
    {
        @trigger_error(\sprintf('Since nowo-tech/ui-kit-bundle 2.49: %s() is deprecated, use getRequestPath(), getRequestRoute() or getRouteParameters() (removed in 3.0).', __METHOD__), \E_USER_DEPRECATED);

        return $this->request;
    }

    /**
     * @deprecated since 2.49, use {@see getRedirectTargetUrl()} / {@see getRedirectStatusCode()}; removed in 3.0
     */
    public function getResponse(): RedirectResponse
    {
        @trigger_error(\sprintf('Since nowo-tech/ui-kit-bundle 2.49: %s() is deprecated, use getRedirectTargetUrl() or getRedirectStatusCode() (removed in 3.0).', __METHOD__), \E_USER_DEPRECATED);

        return $this->response;
    }

    /** 2.49: path of the dialog request (`Request::getPathInfo()`, no query string), e.g. `/orders/7/edit`. */
    public function getRequestPath(): string
    {
        return $this->request->getPathInfo();
    }

    /** 2.49: route name of the dialog request (`_route` attribute), null when unrouted. */
    public function getRequestRoute(): ?string
    {
        $route = $this->request->attributes->get('_route');

        return \is_string($route) ? $route : null;
    }

    /**
     * 2.49: route parameters of the dialog request (`_route_params`), scalar values only (others are dropped).
     *
     * @return array<string, bool|float|int|string|null>
     */
    public function getRouteParameters(): array
    {
        $params = $this->request->attributes->get('_route_params');
        $scalars = [];
        foreach (\is_array($params) ? $params : [] as $name => $value) {
            if (\is_string($name) && (null === $value || \is_scalar($value))) {
                $scalars[$name] = $value;
            }
        }

        return $scalars;
    }

    /** 2.49: the redirect's original target URL as the controller set it (before same-origin reduction). */
    public function getRedirectTargetUrl(): string
    {
        return $this->response->getTargetUrl();
    }

    /** 2.49: the redirect's status code (301, 302, 303, 307 or 308). */
    public function getRedirectStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    /** The validated same-origin path of the answer (path, query and fragment). */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Retargets the answer.
     *
     * @throws InvalidArgumentException when the URL is not a same-origin target
     */
    public function setPath(string $url): void
    {
        $this->path = RemoteDialogTarget::path($url, $this->request); // @igor-ignore — a per-redirect event object (new instance per dispatch), not a shared service
    }

    /** `redirect`, `step` or null (left as a redirect). */
    public function getAnswer(): ?string
    {
        return $this->answer;
    }

    /**
     * @throws InvalidArgumentException for anything but `redirect`, `step` or null
     */
    public function setAnswer(?string $answer): void
    {
        if (null !== $answer && self::ANSWER_REDIRECT !== $answer && self::ANSWER_STEP !== $answer) {
            throw new InvalidArgumentException(\sprintf('A remote dialog answer is "%s", "%s" or null, "%s" given.', self::ANSWER_REDIRECT, self::ANSWER_STEP, $answer));
        }
        $this->answer = $answer; // @igor-ignore — a per-redirect event object (new instance per dispatch), not a shared service
    }
}
