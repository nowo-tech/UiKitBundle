<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Dialog;

use InvalidArgumentException;
use LogicException;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Dialog\RemoteDialog;
use Nowo\UiKitBundle\Dialog\RemoteDialogHeaders;
use Nowo\UiKitBundle\Dialog\RemoteDialogRedirectEvent;
use Nowo\UiKitBundle\Dialog\RemoteDialogRedirectListener;
use Nowo\UiKitBundle\Dialog\RemoteDialogResponder;
use Nowo\UiKitBundle\Tests\Unit\PageContract\PageContractTwig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * 2.33 (UI-1): the step answer of the remote form dialog (`204` + `X-Nowo-Dialog-Location`), configurable header
 * names, step routes and the redirect event of the listener.
 */
final class RemoteDialogStepTest extends TestCase
{
    public function testHeadersDefaultsAndValidation(): void
    {
        $defaults = RemoteDialogHeaders::defaults();
        self::assertSame(['X-Nowo-Dialog', 'X-Nowo-Redirect', 'X-Nowo-Dialog-Location'], [$defaults->request, $defaults->redirect, $defaults->location]);
        self::assertTrue($defaults->isDefault());
        self::assertSame([], $defaults->dialogAttributes());
        self::assertSame('X-Nowo-Dialog-Location', RemoteDialog::LOCATION_HEADER);

        $beacon = new RemoteDialogHeaders('X-Beacon-Dialog', 'X-Beacon-Redirect', 'X-Beacon-Dialog-Location');
        self::assertFalse($beacon->isDefault());
        self::assertSame([
            'data-nowo-ui-remote-form-request-header' => 'X-Beacon-Dialog',
            'data-nowo-ui-remote-form-redirect-header' => 'X-Beacon-Redirect',
            'data-nowo-ui-remote-form-location-header' => 'X-Beacon-Dialog-Location',
        ], $beacon->dialogAttributes());
        self::assertSame(['data-nowo-ui-remote-form-location-header' => 'X-Step'], (new RemoteDialogHeaders(location: 'X-Step'))->dialogAttributes());
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function invalidHeaders(): iterable
    {
        yield 'space' => ['X Dialog', 'X-Nowo-Redirect', 'X-Nowo-Dialog-Location'];
        yield 'colon' => ['X-Nowo-Dialog', 'X:Redirect', 'X-Nowo-Dialog-Location'];
        yield 'crlf' => ['X-Nowo-Dialog', 'X-Nowo-Redirect', "X-Step\r\nSet-Cookie: a=b"];
        yield 'empty' => ['', 'X-Nowo-Redirect', 'X-Nowo-Dialog-Location'];
        yield 'leading dash' => ['-X', 'X-Nowo-Redirect', 'X-Nowo-Dialog-Location'];
        yield 'too long' => [str_repeat('X', 65), 'X-Nowo-Redirect', 'X-Nowo-Dialog-Location'];
        yield 'same names' => ['X-Nowo-Dialog', 'x-nowo-dialog', 'X-Nowo-Dialog-Location'];
    }

    #[DataProvider('invalidHeaders')]
    public function testInvalidHeaderNamesAreRefused(string $request, string $redirect, string $location): void
    {
        self::assertNotNull(RemoteDialogHeaders::invalid($request, $redirect, $location));
        $this->expectException(InvalidArgumentException::class);
        new RemoteDialogHeaders($request, $redirect, $location);
    }

    public function testConfiguredRequestHeaderIsDetected(): void
    {
        $headers = new RemoteDialogHeaders('X-Beacon-Dialog', 'X-Beacon-Redirect', 'X-Beacon-Dialog-Location');
        $request = Request::create('https://app.example/form', 'POST');
        $request->headers->set('X-Beacon-Dialog', '1');
        self::assertTrue(RemoteDialog::isRequest($request, $headers));
        self::assertFalse(RemoteDialog::isRequest($request));
        self::assertSame(['X-Beacon-Dialog'], RemoteDialog::vary(new Response(), $headers)->getVary());

        $responder = new RemoteDialogResponder($this->stack($request), null, $headers);
        self::assertSame($headers, $responder->headers());
        self::assertTrue($responder->isDialog());
        $saved = $responder->redirect('/board/7');
        self::assertSame('/board/7', $saved->headers->get('X-Beacon-Redirect'));
        self::assertFalse($saved->headers->has('X-Nowo-Redirect'));
        self::assertSame(['X-Beacon-Dialog'], $saved->getVary());
    }

    public function testResponderStepForDialogAndPageRequests(): void
    {
        $responder = new RemoteDialogResponder($this->stack($this->dialogRequest()));
        $step = $responder->step('https://app.example/connect/lists?board=7');
        self::assertSame(204, $step->getStatusCode());
        self::assertSame('/connect/lists?board=7', $step->headers->get('X-Nowo-Dialog-Location'));
        self::assertFalse($step->headers->has('X-Nowo-Redirect'));
        self::assertSame(['X-Nowo-Dialog'], $step->getVary());
        self::assertStringContainsString('no-store', (string) $step->headers->get('Cache-Control'));

        $page = (new RemoteDialogResponder($this->stack(Request::create('https://app.example/form', 'POST'))))->step('/connect/lists', 302);
        self::assertInstanceOf(RedirectResponse::class, $page);
        self::assertSame(302, $page->getStatusCode());
        self::assertSame('/connect/lists', $page->getTargetUrl());
        self::assertSame(['X-Nowo-Dialog'], $page->getVary());
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function offOrigin(): iterable
    {
        yield 'other host' => ['https://evil.example/x'];
        yield 'protocol relative' => ['//evil.example/x'];
        yield 'backslash' => ['/\\evil.example'];
        yield 'dot segments' => ['/.//evil.example/x'];
        yield 'encoded dots' => ['/%2e%2e//evil.example'];
        yield 'scheme' => ['javascript:alert(1)'];
        yield 'header injection' => ["/x\r\nSet-Cookie: a=b"];
    }

    #[DataProvider('offOrigin')]
    public function testStepRefusesOtherOrigins(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new RemoteDialogResponder($this->stack($this->dialogRequest())))->step($url);
    }

    public function testStepToRoute(): void
    {
        $urls = $this->createMock(UrlGeneratorInterface::class);
        $urls->method('generate')->with('lists', ['board' => 7])->willReturn('/boards/7/lists');
        $responder = new RemoteDialogResponder($this->stack($this->dialogRequest()), $urls);
        self::assertSame('/boards/7/lists', $responder->stepToRoute('lists', ['board' => 7])->headers->get('X-Nowo-Dialog-Location'));

        $this->expectException(LogicException::class);
        (new RemoteDialogResponder(new RequestStack()))->stepToRoute('x');
    }

    public function testStepRoutesAnswerSteps(): void
    {
        $listener = new RemoteDialogRedirectListener(null, false, ['*_board_connection*', 'task_provider_token']);
        $redirect = new RedirectResponse('https://app.example/p/7/boards/1/connection?step=lists');
        $redirect->headers->set('X-Frame-Options', 'DENY');
        $redirect->headers->setCookie(Cookie::create('flash', 'x'));
        $event = $this->event($this->dialogRequest('app_project_nowo_task_board_board_connection_containers'), $redirect);
        $listener($event);
        $response = $event->getResponse();
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('/p/7/boards/1/connection?step=lists', $response->headers->get('X-Nowo-Dialog-Location'));
        self::assertFalse($response->headers->has('X-Nowo-Redirect'));
        self::assertFalse($response->headers->has('Location'));
        self::assertSame('DENY', $response->headers->get('X-Frame-Options'));
        self::assertSame('flash', $response->headers->getCookies()[0]->getName());

        // Not a step route and convert_redirects off: untouched.
        $other = new RedirectResponse('/elsewhere');
        $event = $this->event($this->dialogRequest('other_route'), $other);
        $listener($event);
        self::assertSame($other, $event->getResponse());

        // A step route answers Vary on every response; a page request keeps its redirect.
        $page = new RedirectResponse('/x');
        $event = $this->event($this->pageRequest('task_provider_token'), $page);
        $listener($event);
        self::assertSame($page, $event->getResponse());
        self::assertSame(['X-Nowo-Dialog'], $page->getVary());
        $html = new Response('<p>form</p>');
        $listener($this->event($this->dialogRequest('general_nowo_task_board_board_connection'), $html));
        self::assertSame(['X-Nowo-Dialog'], $html->getVary());

        // Off-origin targets stay redirects (the browser never follows them from the dialog: redirect: 'manual').
        $off = new RedirectResponse('https://evil.example/x');
        $event = $this->event($this->dialogRequest('task_provider_token'), $off);
        $listener($event);
        self::assertSame($off, $event->getResponse());

        // Sub-requests are ignored.
        $sub = new RedirectResponse('/x');
        $event = $this->event($this->dialogRequest('task_provider_token'), $sub, HttpKernelInterface::SUB_REQUEST);
        $listener($event);
        self::assertSame($sub, $event->getResponse());
    }

    public function testStepRoutesWithConvertRedirectsAndConfiguredHeaders(): void
    {
        $headers = new RemoteDialogHeaders('X-Beacon-Dialog', 'X-Beacon-Redirect', 'X-Beacon-Dialog-Location');
        $listener = new RemoteDialogRedirectListener($headers, true, ['wizard_*']);
        $request = $this->dialogRequest('wizard_two', 'X-Beacon-Dialog');
        $event = $this->event($request, new RedirectResponse('/wizard/3'));
        $listener($event);
        self::assertSame('/wizard/3', $event->getResponse()->headers->get('X-Beacon-Dialog-Location'));
        self::assertSame(['X-Beacon-Dialog'], $event->getResponse()->getVary());

        $event = $this->event($this->dialogRequest('done', 'X-Beacon-Dialog'), new RedirectResponse('/list'));
        $listener($event);
        self::assertSame('/list', $event->getResponse()->headers->get('X-Beacon-Redirect'));
        self::assertFalse($event->getResponse()->headers->has('X-Nowo-Redirect'));

        // The default request header is not a dialog request any more.
        $plain = new RedirectResponse('/list');
        $event = $this->event($this->dialogRequest('done'), $plain);
        $listener($event);
        self::assertSame($plain, $event->getResponse());
    }

    public function testRoutePattern(): void
    {
        self::assertNull(RemoteDialogRedirectListener::routePattern([]));
        self::assertNull(RemoteDialogRedirectListener::routePattern(['', 3]));
        $pattern = (string) RemoteDialogRedirectListener::routePattern(['a.b', 'x_*']);
        self::assertSame(1, preg_match($pattern, 'a.b'));
        self::assertSame(0, preg_match($pattern, 'aXb'));
        self::assertSame(1, preg_match($pattern, 'x_anything'));
        self::assertSame(0, preg_match($pattern, "x_\n".'y_'));
        self::assertSame(0, preg_match($pattern, 'a.b2'));
    }

    public function testEventChangesTheAnswerAndRevalidatesThePath(): void
    {
        $dispatcher = new EventDispatcher();
        $seen = [];
        $dispatcher->addListener(RemoteDialogRedirectEvent::class, static function (RemoteDialogRedirectEvent $event) use (&$seen): void {
            $seen[] = [$event->getRequest()->attributes->get('_route'), $event->getPath(), $event->getAnswer(), $event->getResponse()->getTargetUrl()];
            if ('link' === $event->getRequest()->attributes->get('_route')) {
                $event->setAnswer(RemoteDialogRedirectEvent::ANSWER_REDIRECT);
                $event->setPath('https://app.example/boards/1');
            } elseif ('keep' === $event->getRequest()->attributes->get('_route')) {
                $event->setAnswer(null);
            }
        });
        $listener = new RemoteDialogRedirectListener(null, false, ['link', 'keep', 'next'], $dispatcher);

        $event = $this->event($this->dialogRequest('link'), new RedirectResponse('/boards/1/connection'));
        $listener($event);
        self::assertSame('/boards/1', $event->getResponse()->headers->get('X-Nowo-Redirect'));

        $keep = new RedirectResponse('/x');
        $event = $this->event($this->dialogRequest('keep'), $keep);
        $listener($event);
        self::assertSame($keep, $event->getResponse());

        $event = $this->event($this->dialogRequest('next'), new RedirectResponse('/step/2'));
        $listener($event);
        self::assertSame('/step/2', $event->getResponse()->headers->get('X-Nowo-Dialog-Location'));

        // Without a step route nor convert_redirects the proposed answer is null, still dispatched.
        $event = $this->event($this->dialogRequest('other'), new RedirectResponse('/y'));
        $listener($event);
        self::assertSame(['link', '/boards/1/connection', 'step', '/boards/1/connection'], $seen[0]);
        self::assertSame(['other', '/y', null, '/y'], $seen[3]);
    }

    public function testEventRefusesUnsafeChanges(): void
    {
        $event = new RemoteDialogRedirectEvent($this->dialogRequest('x'), new RedirectResponse('/a'), '/a', null);
        try {
            $event->setPath('https://evil.example/x');
            self::fail('An off-origin path must be refused.');
        } catch (InvalidArgumentException) {
            self::assertSame('/a', $event->getPath());
        }
        $this->expectException(InvalidArgumentException::class);
        $event->setAnswer('close');
    }

    public function testConfigurationAndWiring(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);
        self::assertSame(['X-Nowo-Dialog', 'X-Nowo-Redirect', 'X-Nowo-Dialog-Location'], $container->getDefinition('nowo_ui_kit.remote_dialog_headers')->getArguments());
        self::assertFalse($container->hasDefinition('nowo_ui_kit.remote_dialog_redirect_listener'));
        $responderArgs = $container->getDefinition('nowo_ui_kit.remote_dialog_responder')->getArguments();
        self::assertEquals(new Reference('nowo_ui_kit.remote_dialog_headers'), $responderArgs[2]);
        self::assertEquals(new Reference('nowo_ui_kit.remote_dialog_headers'), $container->getDefinition('nowo_ui_kit.theme_twig_extension')->getArgument('$remoteDialogHeaders'));

        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['remote_dialog' => [
            'step_routes' => ['*_board_connection*', 'task_provider_token'],
            'request_header' => 'X-Beacon-Dialog',
            'redirect_header' => 'X-Beacon-Redirect',
            'location_header' => 'X-Beacon-Dialog-Location',
        ]]], $container);
        self::assertSame(['X-Beacon-Dialog', 'X-Beacon-Redirect', 'X-Beacon-Dialog-Location'], $container->getDefinition('nowo_ui_kit.remote_dialog_headers')->getArguments());
        $listener = $container->getDefinition('nowo_ui_kit.remote_dialog_redirect_listener');
        self::assertFalse($listener->getArgument(1));
        self::assertSame(['*_board_connection*', 'task_provider_token'], $listener->getArgument(2));
        self::assertSame([['event' => 'kernel.response', 'priority' => -10]], $listener->getTag('kernel.event_listener'));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function invalidConfig(): iterable
    {
        yield 'bad header' => [['request_header' => "X-A\r\nB"]];
        yield 'duplicate headers' => [['redirect_header' => 'X-Nowo-Dialog']];
        yield 'bad route pattern' => [['step_routes' => ['a b']]];
        yield 'regex route pattern' => [['step_routes' => ['/.*/']]];
    }

    /**
     * @param array<string, mixed> $remoteDialog
     */
    #[DataProvider('invalidConfig')]
    public function testInvalidConfigurationIsRefused(array $remoteDialog): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new NowoUiKitExtension())->load([['remote_dialog' => $remoteDialog]], new ContainerBuilder());
    }

    public function testPartialPrintsConfiguredHeadersOnly(): void
    {
        $default = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_remote_form_dialog.html.twig', ['id' => 'd', 'stimulus' => true]);
        self::assertStringNotContainsString('-header=', $default);

        $headers = new RemoteDialogHeaders('X-Beacon-Dialog', 'X-Beacon-Redirect', 'X-Beacon-Dialog-Location');
        foreach ([true, false] as $stimulus) {
            $html = PageContractTwig::create(remoteDialogHeaders: $headers)->render('@NowoUiKitBundle/partials/_remote_form_dialog.html.twig', ['id' => 'd', 'stimulus' => $stimulus]);
            self::assertStringContainsString('data-nowo-ui-remote-form-request-header="X-Beacon-Dialog"', $html);
            self::assertStringContainsString('data-nowo-ui-remote-form-redirect-header="X-Beacon-Redirect"', $html);
            self::assertStringContainsString('data-nowo-ui-remote-form-location-header="X-Beacon-Dialog-Location"', $html);
        }

        $request = Request::create('/x');
        $request->headers->set('X-Beacon-Dialog', '1');
        $twig = PageContractTwig::create(request: $request, remoteDialogHeaders: $headers, templates: ['t' => '{{ nowo_ui_dialog_request() ? "dialog" : "page" }}']);
        self::assertSame('dialog', $twig->render('t'));
    }

    private function dialogRequest(string $route = 'form', string $header = 'X-Nowo-Dialog'): Request
    {
        $request = Request::create('https://app.example/form', 'POST');
        $request->headers->set($header, '1');
        $request->attributes->set('_route', $route);

        return $request;
    }

    private function pageRequest(string $route): Request
    {
        $request = Request::create('https://app.example/form', 'POST');
        $request->attributes->set('_route', $route);

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
