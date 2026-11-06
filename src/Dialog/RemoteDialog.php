<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Dialog;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The remote form dialog contract (2.28): the request header the dialog sends (`X-Nowo-Dialog: 1`) and the response
 * header that tells it where to go once saved (`X-Nowo-Redirect`, with a `204`). Since 2.33 a step answer
 * (`204` + `X-Nowo-Dialog-Location`) loads the next step of a multi-step flow into the same dialog, and the three
 * names are configurable ({@see RemoteDialogHeaders}); the constants are the defaults. Static and stateless.
 *
 * The request header is NOT a security signal: any page can send it. Never skip CSRF, authorization or validation
 * because of it; it only selects the fragment template and the success answer.
 */
final class RemoteDialog
{
    public const REQUEST_HEADER = RemoteDialogHeaders::DEFAULT_REQUEST;

    public const REDIRECT_HEADER = RemoteDialogHeaders::DEFAULT_REDIRECT;

    /** 2.33: `204` + this header = the next step of the flow, loaded into the same dialog. */
    public const LOCATION_HEADER = RemoteDialogHeaders::DEFAULT_LOCATION;

    /** Whether the request comes from a remote form dialog (`<request header>: 1`, default `X-Nowo-Dialog`). */
    public static function isRequest(?Request $request, ?RemoteDialogHeaders $headers = null): bool
    {
        return $request instanceof Request && '1' === $request->headers->get($headers->request ?? self::REQUEST_HEADER);
    }

    /** Adds `Vary: <request header>` (keeps the other Vary entries); returns the response. */
    public static function vary(Response $response, ?RemoteDialogHeaders $headers = null): Response
    {
        $response->setVary($headers->request ?? self::REQUEST_HEADER, false);

        return $response;
    }
}
