<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Dialog;

use InvalidArgumentException;
use LogicException;
use Nowo\UiKitBundle\Dialog\RemoteDialog;
use Nowo\UiKitBundle\Dialog\RemoteDialogRedirectListener;
use Nowo\UiKitBundle\Dialog\RemoteDialogResponder;
use Nowo\UiKitBundle\Dialog\RemoteDialogTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * 2.28 remote form dialog, server side: header detection, redirect targets (same-origin paths only), the responder
 * and the opt-in redirect listener.
 */
final class RemoteDialogTest extends TestCase
{
    public function testDetectsDialogRequestsAndAddsVary(): void
    {
        self::assertFalse(RemoteDialog::isRequest(null));
        self::assertFalse(RemoteDialog::isRequest(Request::create('/x')));
        self::assertFalse(RemoteDialog::isRequest($this->dialogRequest('true')));
        self::assertTrue(RemoteDialog::isRequest($this->dialogRequest()));

        $response = new Response();
        $response->setVary('Accept-Language');
        self::assertSame($response, RemoteDialog::vary($response));
        self::assertSame(['Accept-Language', 'X-Nowo-Dialog'], $response->getVary());
    }

    /**
     * @return iterable<string, array{0: string, 1: string|null}>
     */
    public static function targets(): iterable
    {
        yield 'path' => ['/orders/7?tab=alerts#x', '/orders/7?tab=alerts#x'];
        yield 'root' => ['/', '/'];
        yield 'same origin absolute' => ['https://app.example/orders/7?a=1#f', '/orders/7?a=1#f'];
        yield 'same origin, no path' => ['https://app.example', '/'];
        yield 'same origin, explicit port' => ['https://app.example:443/x', '/x'];
        yield 'other host' => ['https://evil.example/x', null];
        yield 'other scheme' => ['http://app.example/x', null];
        yield 'other port' => ['https://app.example:8443/x', null];
        yield 'user info' => ['https://user:pw@app.example/x', null];
        yield 'protocol relative' => ['//evil.example/x', null];
        yield 'backslash' => ['/\\evil.example', null];
        yield 'backslash inside' => ['/a\\b', null];
        yield 'control character' => ["/a\nb", null];
        yield 'javascript' => ['javascript:alert(1)', null];
        yield 'relative' => ['orders/7', null];
        yield 'empty' => ['', null];
        yield 'too long' => ['/'.str_repeat('a', RemoteDialogTarget::MAX_LENGTH), null];
        yield 'absolute with double-slash path' => ['https://app.example//evil.example', null];
        yield 'malformed' => ['https://', null];
        // REQ-SEC-004 ES-M1: dot segments would normalise to //evil.example (another origin).
        foreach (['/.//evil.example/x', '/..//evil.example/x', '/%2e//evil.example/x', '/%2E%2E//evil.example/x', '/a/..//evil.example/x', '/%2e%2E//evil.example/x', '/.%2e//evil.example/x', '/a/%2E/b', '/a/./b', '/a/..', '/.', '/%252e//evil.example/x', '/%2F%2Fevil.example', '/a%5c..%5cb', '/./?x=1', '/files/a%2Eb', '/x%2etxt'] as $dotted) {
            yield 'dot segment '.$dotted => [$dotted, null];
        }
        yield 'absolute same origin with a dot segment' => ['https://app.example/.//evil.example/x', null];
        yield 'absolute same origin with an encoded dot segment' => ['https://app.example/%2E%2E//evil.example', null];
        yield 'dots inside a name' => ['/files/a..b/.well-known/x.y', '/files/a..b/.well-known/x.y'];
        yield 'dots in the query only' => ['/x?next=/../evil#..', '/x?next=/../evil#..'];
    }

    #[DataProvider('targets')]
    public function testRedirectTargetsAreSameOriginPaths(string $url, ?string $expected): void
    {
        self::assertSame($expected, RemoteDialogTarget::tryPath($url, Request::create('https://app.example/form')));
    }

    public function testAbsoluteTargetsNeedARequestAndBadTargetsThrow(): void
    {
        self::assertNull(RemoteDialogTarget::tryPath('https://app.example/x', null));
        self::assertSame('/x', RemoteDialogTarget::path('/x', null));
        $this->expectException(InvalidArgumentException::class);
        RemoteDialogTarget::path('https://evil.example/', Request::create('https://app.example/'));
    }

    public function testResponderAnswers204WithTheRedirectHeaderToDialogRequests(): void
    {
        $responder = new RemoteDialogResponder($this->stack($this->dialogRequest()));
        self::assertTrue($responder->isDialog());
        $response = $responder->redirect('https://app.example/settings/alerts');
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('/settings/alerts', $response->headers->get('X-Nowo-Redirect'));
        self::assertContains('X-Nowo-Dialog', $response->getVary());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame('_fragment.html.twig', $responder->template('page.html.twig', '_fragment.html.twig'));
    }

    public function testResponderRedirectsNormalRequests(): void
    {
        $responder = new RemoteDialogResponder($this->stack(Request::create('https://app.example/form')));
        self::assertFalse($responder->isDialog());
        $response = $responder->redirect('/settings', Response::HTTP_FOUND);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/settings', $response->getTargetUrl());
        self::assertContains('X-Nowo-Dialog', $response->getVary());
        self::assertSame('page.html.twig', $responder->template('page.html.twig', '_fragment.html.twig'));
        self::assertTrue($responder->isDialog($this->dialogRequest()));
    }

    public function testResponderFragmentAddsVary(): void
    {
        $response = (new RemoteDialogResponder())->fragment(new Response('<form></form>', 422));
        self::assertContains('X-Nowo-Dialog', $response->getVary());
        self::assertSame(422, $response->getStatusCode());
    }

    public function testResponderRefusesDotSegmentTargets(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RemoteDialogResponder($this->stack($this->dialogRequest())))->redirect('/a/..//evil.example/x');
    }

    public function testResponderRefusesOtherOrigins(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RemoteDialogResponder($this->stack(Request::create('https://app.example/'))))->redirect('https://evil.example/');
    }

    public function testRedirectToRouteUsesTheRouter(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->expects(self::once())->method('generate')->with('settings', ['id' => 7])->willReturn('/projects/7/settings');
        $responder = new RemoteDialogResponder($this->stack($this->dialogRequest()), $urls);
        self::assertSame('/projects/7/settings', $responder->redirectToRoute('settings', ['id' => 7])->headers->get('X-Nowo-Redirect'));
    }

    public function testRedirectToRouteNeedsTheRouter(): void
    {
        $this->expectException(LogicException::class);
        (new RemoteDialogResponder(new RequestStack()))->redirectToRoute('x');
    }

    public function testListenerConvertsRedirectsOfMainDialogRequestsOnly(): void
    {
        $listener = new RemoteDialogRedirectListener();
        $redirect = new RedirectResponse('/settings');
        $redirect->headers->setCookie(Cookie::create('flash', 'x'));
        $redirect->headers->set('X-Frame-Options', 'DENY');
        $redirect->headers->set('Content-Security-Policy', "default-src 'self'");
        $event = $this->event($this->dialogRequest(), $redirect);
        $listener($event);
        self::assertSame(204, $event->getResponse()->getStatusCode());
        self::assertSame('/settings', $event->getResponse()->headers->get('X-Nowo-Redirect'));
        self::assertCount(1, $event->getResponse()->headers->getCookies());
        self::assertSame('DENY', $event->getResponse()->headers->get('X-Frame-Options'));
        self::assertSame("default-src 'self'", $event->getResponse()->headers->get('Content-Security-Policy'));
        self::assertNull($event->getResponse()->headers->get('Location'));
        self::assertStringContainsString('no-store', (string) $event->getResponse()->headers->get('Cache-Control'));

        foreach ([
            $this->event(Request::create('https://app.example/form'), new RedirectResponse('/settings')),
            $this->event($this->dialogRequest(), new RedirectResponse('/settings'), HttpKernelInterface::SUB_REQUEST),
            $this->event($this->dialogRequest(), new Response('page')),
            $this->event($this->dialogRequest(), new RedirectResponse('https://evil.example/')),
            $this->event($this->dialogRequest(), new RedirectResponse('/.//evil.example/x')),
        ] as $untouched) {
            $before = $untouched->getResponse();
            $listener($untouched);
            self::assertSame($before, $untouched->getResponse());
        }
    }

    private function dialogRequest(string $value = '1'): Request
    {
        $request = Request::create('https://app.example/form', 'POST');
        $request->headers->set('X-Nowo-Dialog', $value);

        return $request;
    }

    private function stack(Request $request): RequestStack
    {
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }

    private function event(Request $request, Response $response, int $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent(new class implements HttpKernelInterface {
            public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
            {
                return new Response();
            }
        }, $request, $type, $response);
    }
}
