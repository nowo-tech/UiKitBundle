<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Dialog;

use Nowo\UiKitBundle\Dialog\RemoteDialogRedirectEvent;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * 2.49 (REQ-ARCH-001 rule 3): the request-free accessors give the same data as the deprecated HTTP getters.
 */
final class RemoteDialogRedirectEventTest extends TestCase
{
    public function testScalarAccessorsMatchTheHttpGetters(): void
    {
        $request = Request::create('https://app.example/p/7/boards/1/connection?step=2');
        $request->attributes->set('_route', 'board_connection');
        $request->attributes->set('_route_params', ['project' => 'p7', 'boardId' => 1, 'flag' => false, 'obj' => new stdClass(), 'list' => [1]]);
        $response = new RedirectResponse('https://app.example/boards/1?x=1#top', 303);
        $event = new RemoteDialogRedirectEvent($request, $response, '/boards/1?x=1#top', RemoteDialogRedirectEvent::ANSWER_STEP);

        self::assertSame('/p/7/boards/1/connection', $event->getRequestPath());
        self::assertSame($event->getRequest()->getPathInfo(), $event->getRequestPath());
        self::assertSame('board_connection', $event->getRequestRoute());
        self::assertSame(['project' => 'p7', 'boardId' => 1, 'flag' => false], $event->getRouteParameters());
        self::assertSame('https://app.example/boards/1?x=1#top', $event->getRedirectTargetUrl());
        self::assertSame($event->getResponse()->getTargetUrl(), $event->getRedirectTargetUrl());
        self::assertSame(303, $event->getRedirectStatusCode());
        self::assertSame('/boards/1?x=1#top', $event->getPath());
        self::assertSame(RemoteDialogRedirectEvent::ANSWER_STEP, $event->getAnswer());
    }

    public function testUnroutedRequest(): void
    {
        $request = Request::create('/x');
        $request->attributes->set('_route_params', 'not-an-array');
        $event = new RemoteDialogRedirectEvent($request, new RedirectResponse('/y'), '/y', null);

        self::assertNull($event->getRequestRoute());
        self::assertSame([], $event->getRouteParameters());
        self::assertSame('/x', $event->getRequestPath());
        self::assertSame(302, $event->getRedirectStatusCode());
    }

    /**
     * Audit MR-U-I1: the HTTP getters still work and trigger a runtime deprecation.
     */
    public function testHttpGettersTriggerADeprecation(): void
    {
        $request = Request::create('/x');
        $response = new RedirectResponse('/y');
        $event = new RemoteDialogRedirectEvent($request, $response, '/y', null);
        $deprecations = [];
        set_error_handler(static function (int $type, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            self::assertSame($request, $event->getRequest());
            self::assertSame($response, $event->getResponse());
        } finally {
            restore_error_handler();
        }

        self::assertCount(2, $deprecations);
        self::assertStringContainsString('Since nowo-tech/ui-kit-bundle 2.49', $deprecations[0]);
        self::assertStringContainsString('getRequest()', $deprecations[0]);
        self::assertStringContainsString('getResponse()', $deprecations[1]);
    }
}
