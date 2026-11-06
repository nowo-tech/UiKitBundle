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
 */
final class RemoteDialogRedirectEvent extends Event
{
    public const ANSWER_REDIRECT = 'redirect';

    public const ANSWER_STEP = 'step';

    public function __construct(
        private readonly Request $request,
        private readonly RedirectResponse $response,
        private string $path,
        private ?string $answer,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): RedirectResponse
    {
        return $this->response;
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
        $this->path = RemoteDialogTarget::path($url, $this->request);
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
        $this->answer = $answer;
    }
}
