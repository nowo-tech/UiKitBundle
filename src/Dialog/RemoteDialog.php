<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The remote form dialog contract (2.28): the request header the dialog sends (`X-Nowo-Dialog: 1`) and the response
 * header that tells it where to go once saved (`X-Nowo-Redirect`, with a `204`). Static and stateless.
 *
 * The request header is NOT a security signal: any page can send it. Never skip CSRF, authorization or validation
 * because of it; it only selects the fragment template and the success answer.
 */
final class RemoteDialog
{
    public const REQUEST_HEADER = 'X-Nowo-Dialog';

    public const REDIRECT_HEADER = 'X-Nowo-Redirect';

    /** Whether the request comes from a remote form dialog (`X-Nowo-Dialog: 1`). */
    public static function isRequest(?Request $request): bool
    {
        return $request instanceof Request && '1' === $request->headers->get(self::REQUEST_HEADER);
    }

    /** Adds `Vary: X-Nowo-Dialog` (keeps the other Vary entries); returns the response. */
    public static function vary(Response $response): Response
    {
        $response->setVary(self::REQUEST_HEADER, false);

        return $response;
    }
}
