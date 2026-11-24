<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Dialog;

use InvalidArgumentException;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Dialog\RemoteDialogContent;
use Nowo\UiKitBundle\Dialog\RemoteDialogContentMap;
use Nowo\UiKitBundle\Dialog\RemoteDialogDirectGetListener;
use Nowo\UiKitBundle\Dialog\RemoteDialogHeaders;
use Nowo\UiKitBundle\Dialog\RemoteDialogReopen;
use Nowo\UiKitBundle\Tests\Unit\PageContract\PageContractTwig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * 2.73: the pieces of the direct-GET contract of remote dialog content — the route entry and map, the configuration
 * and its wiring, the navigation signal, the listener's guards and the reopen attributes without a router.
 */
final class RemoteDialogContentTest extends TestCase
{
    public function testEntryAndMap(): void
    {
        $map = new RemoteDialogContentMap([
            'b_step' => ['page' => 'page', 'dialog' => 'd'],
            'a_step' => new RemoteDialogContent('a_step', 'page', 'd', ['tenant'], true),
            'c_step' => ['page' => 'page', 'dialog' => 'other'],
        ]);
        self::assertFalse($map->isEmpty());
        self::assertSame('_dialog', $map->parameter);
        self::assertSame(['tenant'], $map->get('a_step')?->pageParameters);
        self::assertTrue($map->get('a_step')?->redirectSameOrigin);
        self::assertFalse($map->get('b_step')?->redirectSameOrigin);
        self::assertNull($map->get('page'));
        self::assertNull($map->get(null));
        self::assertSame(['a_step', 'b_step'], $map->routesOf('page', 'd'));
        self::assertSame([], $map->routesOf('page', 'nope'));
        self::assertTrue((new RemoteDialogContentMap())->isEmpty());
        self::assertSame('step', (new RemoteDialogContentMap([], 'step'))->parameter);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: array<mixed>, 4: string}>
     */
    public static function invalidEntries(): iterable
    {
        yield 'route with a slash' => ['a/b', 'page', 'd', [], 'dialog route'];
        yield 'empty page' => ['a', '', 'd', [], 'page route'];
        yield 'own page' => ['a', 'a', 'd', [], 'own owning page'];
        yield 'dialog id with a space' => ['a', 'p', 'x y', [], 'element id'];
        yield 'dialog id starting with a digit' => ['a', 'p', '1d', [], 'element id'];
        yield 'page parameter with a bracket' => ['a', 'p', 'd', ['x[y]'], 'page_parameters'];
        yield 'page parameter not a string' => ['a', 'p', 'd', [3], 'page_parameters'];
    }

    /**
     * @param array<mixed> $pageParameters
     */
    #[DataProvider('invalidEntries')]
    public function testInvalidEntriesAreRefused(string $route, string $page, string $dialog, array $pageParameters, string $message): void
    {
        self::assertStringContainsString($message, (string) RemoteDialogContent::invalid($route, $page, $dialog, $pageParameters));
        $this->expectException(InvalidArgumentException::class);
        /* @phpstan-ignore argument.type (invalid on purpose) */
        new RemoteDialogContent($route, $page, $dialog, $pageParameters);
    }

    public function testInvalidParameterNameIsRefused(): void
    {
        self::assertFalse(RemoteDialogContent::isParameterName('a[b]'));
        self::assertFalse(RemoteDialogContent::isParameterName(''));
        self::assertTrue(RemoteDialogContent::isParameterName('_dialog'));
        $this->expectException(InvalidArgumentException::class);
        new RemoteDialogContentMap([], 'a-b');
    }

    public function testOffByDefault(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);

        self::assertFalse($container->hasDefinition('nowo_ui_kit.remote_dialog_direct_get_listener'));
        self::assertSame([[], '_dialog'], $container->getDefinition('nowo_ui_kit.remote_dialog_content_map')->getArguments());
        self::assertTrue($container->hasDefinition('nowo_ui_kit.remote_dialog_reopen'));
    }

    public function testConfiguredRoutesRegisterTheListenerAfterTheController(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([
            ['remote_dialog' => ['direct_get' => ['routes' => ['kit-step.one' => ['page' => 'kit_page', 'dialog' => 'kit-dialog']]]]],
            ['remote_dialog' => ['direct_get' => ['query_parameter' => 'reopen', 'routes' => ['host_step' => ['page' => 'host_page', 'dialog' => 'host-dialog', 'page_parameters' => ['id'], 'redirect_same_origin' => true]]]]],
        ], $container);

        $listener = $container->getDefinition('nowo_ui_kit.remote_dialog_direct_get_listener');
        self::assertSame([['event' => 'kernel.response', 'priority' => -5]], $listener->getTag('kernel.event_listener'));
        self::assertSame([
            [
                'kit-step.one' => ['page' => 'kit_page', 'dialog' => 'kit-dialog', 'page_parameters' => [], 'redirect_same_origin' => false],
                'host_step' => ['page' => 'host_page', 'dialog' => 'host-dialog', 'page_parameters' => ['id'], 'redirect_same_origin' => true],
            ],
            'reopen',
        ], $container->getDefinition('nowo_ui_kit.remote_dialog_content_map')->getArguments());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'parameter with a bracket' => [['query_parameter' => 'a[b]']];
        yield 'missing dialog' => [['routes' => ['a' => ['page' => 'p']]]];
        yield 'invalid dialog id' => [['routes' => ['a' => ['page' => 'p', 'dialog' => '"><x']]]];
        yield 'own page' => [['routes' => ['a' => ['page' => 'a', 'dialog' => 'd']]]];
    }

    /**
     * @param array<string, mixed> $directGet
     */
    #[DataProvider('invalidConfigs')]
    public function testInvalidConfigurationIsRefused(array $directGet): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new NowoUiKitExtension())->load([['remote_dialog' => ['direct_get' => $directGet]]], new ContainerBuilder());
    }

    public function testTopLevelNavigationSignal(): void
    {
        $navigate = static fn (array $headers): Request => Request::create('/x', 'GET', [], [], [], $headers);
        self::assertTrue(RemoteDialogDirectGetListener::isTopLevelNavigation($navigate(['HTTP_SEC_FETCH_MODE' => 'navigate'])));
        self::assertTrue(RemoteDialogDirectGetListener::isTopLevelNavigation($navigate(['HTTP_SEC_FETCH_MODE' => 'Navigate', 'HTTP_SEC_FETCH_DEST' => 'Document'])));
        self::assertFalse(RemoteDialogDirectGetListener::isTopLevelNavigation($navigate([])));
        self::assertFalse(RemoteDialogDirectGetListener::isTopLevelNavigation($navigate(['HTTP_SEC_FETCH_MODE' => 'navigate', 'HTTP_SEC_FETCH_DEST' => 'embed'])));
        // A host with its own request header name: that header marks the dialog fetch.
        $headers = new RemoteDialogHeaders('X-Acme-Dialog', 'X-Acme-Redirect', 'X-Acme-Location');
        self::assertFalse(RemoteDialogDirectGetListener::isTopLevelNavigation($navigate(['HTTP_SEC_FETCH_MODE' => 'navigate', 'HTTP_X_ACME_DIALOG' => '1']), $headers));
        self::assertTrue(RemoteDialogDirectGetListener::isTopLevelNavigation($navigate(['HTTP_SEC_FETCH_MODE' => 'navigate', 'HTTP_X_NOWO_DIALOG' => '1']), $headers));
    }

    private function reopen(?Request $current = null): RemoteDialogReopen
    {
        $routes = new RouteCollection();
        $routes->add('page', new Route('/page'));
        $routes->add('step', new Route('/step/{id}', [], ['id' => '\d+']));
        $stack = new RequestStack();
        if ($current instanceof Request) {
            $stack->push($current);
        }

        return new RemoteDialogReopen(new RemoteDialogContentMap(['step' => ['page' => 'page', 'dialog' => 'd']]), new UrlGenerator($routes, new RequestContext()), $stack);
    }

    private function stepRequest(string $method = 'GET'): Request
    {
        $request = Request::create('/step/7', $method, [], [], [], ['HTTP_SEC_FETCH_MODE' => 'navigate']);
        $request->attributes->add(['_route' => 'step', '_route_params' => ['id' => '7', 'flag' => true]]);

        return $request;
    }

    public function testListenerRedirectsAMainGetAndHeadOnly(): void
    {
        $listener = new RemoteDialogDirectGetListener($this->reopen());
        $kernel = $this->createStub(HttpKernelInterface::class);

        $event = new ResponseEvent($kernel, $this->stepRequest(), HttpKernelInterface::MAIN_REQUEST, new Response('<p>x</p>'));
        $listener($event);
        self::assertSame(302, $event->getResponse()->getStatusCode());
        self::assertSame('/page?_dialog%5Broute%5D=step&_dialog%5Bparams%5D%5Bid%5D=7&_dialog%5Bparams%5D%5Bflag%5D=1', $event->getResponse()->headers->get('Location'));

        $head = new ResponseEvent($kernel, $this->stepRequest('HEAD'), HttpKernelInterface::MAIN_REQUEST, new Response(''));
        $listener($head);
        self::assertSame(302, $head->getResponse()->getStatusCode());

        $sub = new ResponseEvent($kernel, $this->stepRequest(), HttpKernelInterface::SUB_REQUEST, new Response('<p>x</p>'));
        $listener($sub);
        self::assertSame(200, $sub->getResponse()->getStatusCode());

        $put = new ResponseEvent($kernel, $this->stepRequest('PUT'), HttpKernelInterface::MAIN_REQUEST, new Response('<p>x</p>'));
        $listener($put);
        self::assertSame(200, $put->getResponse()->getStatusCode());

        $unrouted = Request::create('/x', 'GET', [], [], [], ['HTTP_SEC_FETCH_MODE' => 'navigate']);
        $plain = new ResponseEvent($kernel, $unrouted, HttpKernelInterface::MAIN_REQUEST, new Response('<p>x</p>'));
        $listener($plain);
        self::assertSame([], $plain->getResponse()->getVary());
    }

    public function testReopenWithoutRouterOrRequestDoesNothing(): void
    {
        $bare = new RemoteDialogReopen(new RemoteDialogContentMap(['step' => ['page' => 'page', 'dialog' => 'd']]));
        $entry = $bare->map()->get('step');
        self::assertInstanceOf(RemoteDialogContent::class, $entry);
        self::assertNull($bare->redirectTarget($this->stepRequest(), $entry));
        self::assertSame([], $bare->dialogAttributes('d'));

        $page = Request::create('/page', 'GET', ['_dialog' => ['route' => 'step', 'params' => ['id' => '7']]]);
        $page->attributes->set('_route', 'page');
        self::assertSame([
            'data-nowo-ui-remote-form-reopen-param' => '_dialog',
            'data-nowo-ui-remote-form-reopen-routes' => 'step',
        ], $bare->dialogAttributes('d', $page));
        self::assertSame([
            'data-nowo-ui-remote-form-reopen-param' => '_dialog',
            'data-nowo-ui-remote-form-reopen-routes' => 'step',
            'data-nowo-ui-remote-form-reopen-route' => 'step',
            'data-nowo-ui-remote-form-reopen-url' => '/step/7',
        ], $this->reopen($page)->dialogAttributes('d'));
        self::assertSame([], $this->reopen($page)->dialogAttributes('other'));
        self::assertSame([], (new RemoteDialogReopen(new RemoteDialogContentMap()))->dialogAttributes('d', $page));
    }

    public function testAMissingPageParameterLeavesTheAnswer(): void
    {
        $routes = new RouteCollection();
        $routes->add('page', new Route('/{tenant}/page'));
        $reopen = new RemoteDialogReopen(new RemoteDialogContentMap(['step' => ['page' => 'page', 'dialog' => 'd', 'page_parameters' => ['tenant']]]), new UrlGenerator($routes, new RequestContext()));
        $entry = $reopen->map()->get('step');
        self::assertInstanceOf(RemoteDialogContent::class, $entry);

        self::assertNull($reopen->redirectTarget($this->stepRequest(), $entry));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: array<string, mixed>}>
     */
    public static function valuesThatCannotBeCarried(): iterable
    {
        yield 'nested query value' => [['id' => '7'], ['filter' => ['x' => '1']]];
        yield 'query value over 512 characters' => [['id' => '7'], ['q' => str_repeat('a', 513)]];
        yield 'integer query key' => [['id' => '7'], ['x']];
        yield 'non-scalar route parameter' => [['id' => '7', 'list' => ['x']], []];
        yield 'more than 32 values' => [['id' => '7'], array_fill_keys(array_map(static fn (int $i): string => 'p'.$i, range(1, 32)), 'x')];
    }

    /**
     * 2.73 audit L5: a value that cannot be carried whole keeps the route's own answer (no redirect to another state).
     *
     * @param array<string, mixed> $routeParams
     * @param array<mixed>         $query
     */
    #[DataProvider('valuesThatCannotBeCarried')]
    public function testARedirectThatWouldDropAValueIsNotMade(array $routeParams, array $query): void
    {
        $request = Request::create('/step/7', 'GET', $query, [], [], ['HTTP_SEC_FETCH_MODE' => 'navigate']);
        $request->attributes->add(['_route' => 'step', '_route_params' => $routeParams]);
        $reopen = $this->reopen();
        $entry = $reopen->map()->get('step');
        self::assertInstanceOf(RemoteDialogContent::class, $entry);
        self::assertNull($reopen->redirectTarget($request, $entry));

        $event = new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response('<p>x</p>'));
        (new RemoteDialogDirectGetListener($reopen))($event);
        self::assertSame(200, $event->getResponse()->getStatusCode());
    }

    public function testExactly32ValuesAndAnOldReopenParameterAreCarried(): void
    {
        $query = array_fill_keys(array_map(static fn (int $i): string => 'p'.$i, range(1, 31)), 'x') + ['_dialog' => ['route' => 'stale']];
        $request = Request::create('/step/7', 'GET', $query);
        $request->attributes->add(['_route' => 'step', '_route_params' => ['id' => '7']]);
        $reopen = $this->reopen();
        $entry = $reopen->map()->get('step');
        self::assertInstanceOf(RemoteDialogContent::class, $entry);
        $target = (string) $reopen->redirectTarget($request, $entry);
        parse_str((string) parse_url($target, \PHP_URL_QUERY), $parsed);
        self::assertIsArray($parsed['_dialog'] ?? null);
        self::assertSame('step', $parsed['_dialog']['route']);
        self::assertCount(32, $parsed['_dialog']['params']);
    }

    /**
     * 2.73 audit L3: an owning page that is itself a marked route would redirect again (A → B → A loop).
     */
    public function testAnOwningPageThatIsAMarkedRouteIsRefused(): void
    {
        self::assertNotNull(RemoteDialogContentMap::cycle(['a' => ['page' => 'b'], 'b' => ['page' => 'a']]));
        self::assertNull(RemoteDialogContentMap::cycle(['a' => ['page' => 'p'], 'b' => ['page' => 'p']]));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('is itself a marked dialog route');
        new RemoteDialogContentMap(['a' => ['page' => 'b', 'dialog' => 'd'], 'b' => ['page' => 'c', 'dialog' => 'd']]);
    }

    /**
     * @return iterable<string, array{0: list<array<string, mixed>>}>
     */
    public static function cyclicConfigs(): iterable
    {
        yield 'two routes owning each other' => [[['routes' => ['a' => ['page' => 'b', 'dialog' => 'd'], 'b' => ['page' => 'a', 'dialog' => 'd']]]]];
        yield 'chain' => [[['routes' => ['a' => ['page' => 'b', 'dialog' => 'd'], 'b' => ['page' => 'c', 'dialog' => 'd']]]]];
        yield 'kit entry plus host entry' => [[['routes' => ['kit_step' => ['page' => 'kit_page', 'dialog' => 'd']]], ['routes' => ['kit_page' => ['page' => 'kit_step', 'dialog' => 'd']]]]];
    }

    /**
     * @param list<array<string, mixed>> $configs
     */
    #[DataProvider('cyclicConfigs')]
    public function testACyclicConfigurationIsRefusedAtBuild(array $configs): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('is itself a marked dialog route');
        (new NowoUiKitExtension())->load(array_map(static fn (array $directGet): array => ['remote_dialog' => ['direct_get' => $directGet]], $configs), new ContainerBuilder());
    }

    /**
     * 2.73 audit I8: a host entry replaces a kit's entry whole (no merged page_parameters), and `false` unmarks it.
     */
    public function testAHostEntryReplacesOrUnmarksAKitEntry(): void
    {
        $kit = ['remote_dialog' => ['direct_get' => ['routes' => [
            'kit_step' => ['page' => 'kit_page', 'dialog' => 'kit-dialog', 'page_parameters' => ['tenant'], 'redirect_same_origin' => true],
            'kit_other' => ['page' => 'kit_page', 'dialog' => 'kit-dialog'],
            'kit_third' => ['page' => 'kit_page', 'dialog' => 'kit-dialog'],
        ]]]];
        $host = ['remote_dialog' => ['direct_get' => ['routes' => [
            'kit_step' => ['page' => 'host_page', 'dialog' => 'host-dialog', 'page_parameters' => ['id']],
            'kit_other' => false,
            'kit_third' => ['enabled' => false],
        ]]]];
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([$kit, $host], $container);

        self::assertSame([
            ['kit_step' => ['page' => 'host_page', 'dialog' => 'host-dialog', 'page_parameters' => ['id'], 'redirect_same_origin' => false]],
            '_dialog',
        ], $container->getDefinition('nowo_ui_kit.remote_dialog_content_map')->getArguments());

        $unmarked = new ContainerBuilder();
        (new NowoUiKitExtension())->load([$kit, ['remote_dialog' => ['direct_get' => ['routes' => ['kit_step' => false, 'kit_other' => false, 'kit_third' => false]]]]], $unmarked);
        self::assertSame([[], '_dialog'], $unmarked->getDefinition('nowo_ui_kit.remote_dialog_content_map')->getArguments());
        self::assertFalse($unmarked->hasDefinition('nowo_ui_kit.remote_dialog_direct_get_listener'));
    }

    public function testTooManyReopenParametersAreIgnored(): void
    {
        $page = Request::create('/page', 'GET', ['_dialog' => ['route' => 'step', 'params' => ['id' => '7'] + array_fill_keys(array_map(static fn (int $i): string => 'p'.$i, range(1, 40)), 'x')]]);
        $page->attributes->set('_route', 'page');
        self::assertArrayNotHasKey('data-nowo-ui-remote-form-reopen-url', $this->reopen($page)->dialogAttributes('d'));
    }

    public function testPartialWithoutTheServicePrintsNothingExtra(): void
    {
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_remote_form_dialog.html.twig', ['id' => 'd']);
        self::assertStringNotContainsString('reopen', $html);
    }
}
