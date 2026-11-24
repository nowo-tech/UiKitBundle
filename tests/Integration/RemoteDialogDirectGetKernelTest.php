<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Integration;

use Nowo\UiKitBundle\NowoUiKitBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

/**
 * 2.73: remote dialog content opened directly, end to end in a booted kernel — a top-level GET of a marked route is
 * redirected to its owning page with the reopen query, and that page prints the dialog's safe list and the target the
 * router generated again. Dialog fetches, denied requests, POSTs and unmarked routes are unchanged.
 */
final class RemoteDialogDirectGetKernelTest extends TestCase
{
    private const NAVIGATE = ['HTTP_SEC_FETCH_MODE' => 'navigate', 'HTTP_SEC_FETCH_DEST' => 'document'];

    private static ?DirectGetTestKernel $kernel = null;

    private static ?string $dir = null;

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir().'/nowo-ui-kit-direct-get-'.bin2hex(random_bytes(4));
        self::$kernel = new DirectGetTestKernel(self::$dir);
        self::$kernel->boot();
    }

    public static function tearDownAfterClass(): void
    {
        self::$kernel?->shutdown();
        if (null !== self::$dir) {
            (new Filesystem())->remove(self::$dir);
        }
        self::$kernel = null;
    }

    /**
     * @param array<string, string> $server
     * @param array<string, mixed>  $parameters
     */
    private function request(string $uri, array $server = [], string $method = 'GET', array $parameters = []): Response
    {
        \assert(self::$kernel instanceof DirectGetTestKernel);
        $response = self::$kernel->handle(Request::create($uri, $method, $parameters, [], [], $server));
        self::$kernel->terminate(Request::create($uri), $response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function query(string $location): array
    {
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);

        return $query;
    }

    public function testDirectGetOfAMarkedRouteRedirectsToTheOwningPageKeepingItsParameters(): void
    {
        $response = $this->request('/t/acme/import/42/mapping?tab=columns', self::NAVIGATE);

        self::assertSame(302, $response->getStatusCode());
        $location = (string) $response->headers->get('Location');
        self::assertStringStartsWith('/t/acme/import?', $location);
        self::assertSame(['_dialog' => ['route' => 'import_mapping', 'params' => ['tenant' => 'acme', 'id' => '42', 'tab' => 'columns']]], $this->query($location));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertSame(['X-Nowo-Dialog', 'Sec-Fetch-Mode', 'Sec-Fetch-Dest', 'Sec-Fetch-Site'], $response->getVary());
        self::assertSame('kept', $response->headers->getCookies()[0]->getValue());
    }

    public function testANavigationFromAnotherSiteOrNoneRedirects(): void
    {
        foreach (['none', 'cross-site', 'same-site'] as $site) {
            self::assertSame(302, $this->request('/t/acme/import/42/mapping', ['HTTP_SEC_FETCH_SITE' => $site] + self::NAVIGATE)->getStatusCode(), $site);
        }
    }

    public function testEachMarkedRouteRedirectsWithItsOwnParameters(): void
    {
        // redirect_same_origin: this route has no page of its own, so a same-origin link is redirected too.
        $response = $this->request('/t/acme/import/upload', ['HTTP_SEC_FETCH_MODE' => 'navigate', 'HTTP_SEC_FETCH_SITE' => 'same-origin']);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame(['_dialog' => ['route' => 'import_upload', 'params' => ['tenant' => 'acme']]], $this->query((string) $response->headers->get('Location')));
    }

    /**
     * @return iterable<string, array{0: array<string, string>}>
     */
    public static function notNavigations(): iterable
    {
        yield 'dialog fetch' => [['HTTP_X_NOWO_DIALOG' => '1', 'HTTP_SEC_FETCH_MODE' => 'same-origin', 'HTTP_SEC_FETCH_DEST' => 'empty']];
        yield 'dialog header wins over navigate' => [['HTTP_X_NOWO_DIALOG' => '1'] + self::NAVIGATE];
        yield 'no fetch metadata (old browser, script)' => [[]];
        yield 'iframe' => [['HTTP_SEC_FETCH_MODE' => 'navigate', 'HTTP_SEC_FETCH_DEST' => 'iframe']];
        yield 'xhr' => [['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'] + self::NAVIGATE];
        yield 'turbo frame' => [['HTTP_TURBO_FRAME' => 'import'] + self::NAVIGATE];
        yield 'cors fetch' => [['HTTP_SEC_FETCH_MODE' => 'cors']];
        yield 'link of this origin (no-JS fallback, new tab)' => [['HTTP_SEC_FETCH_SITE' => 'same-origin'] + self::NAVIGATE];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('notNavigations')]
    public function testAnythingButATopLevelNavigationGetsTheFragment(array $server): void
    {
        $response = $this->request('/t/acme/import/42/mapping', $server);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<form data-step="mapping-42"></form>', $response->getContent());
        self::assertContains('Sec-Fetch-Mode', $response->getVary());
        self::assertContains('X-Nowo-Dialog', $response->getVary());
    }

    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function deniedRequests(): iterable
    {
        yield 'hidden from this profile' => ['/t/acme/import/404/mapping', 404];
        yield 'forbidden' => ['/t/acme/import/403/mapping', 403];
    }

    #[DataProvider('deniedRequests')]
    public function testADeniedRequestKeepsItsErrorAndIsNeverRedirected(string $uri, int $status): void
    {
        $response = $this->request($uri, self::NAVIGATE);

        self::assertSame($status, $response->getStatusCode());
        self::assertFalse($response->headers->has('Location'));
    }

    public function testPostsAndTheirReRendersAreUnchanged(): void
    {
        $invalid = $this->request('/t/acme/import/42/mapping', self::NAVIGATE, 'POST');
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame('<form data-step="mapping-42" data-invalid></form>', $invalid->getContent());

        $saved = $this->request('/t/acme/import/42/mapping', self::NAVIGATE, 'POST', ['ok' => '1']);
        self::assertSame(200, $saved->getStatusCode());
        self::assertFalse($saved->headers->has('Location'));
    }

    public function testANonHtmlAnswerOfAMarkedRouteIsUnchanged(): void
    {
        $response = $this->request('/t/acme/import/42/mapping?format=json', self::NAVIGATE);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{}', $response->getContent());
    }

    public function testAnUnmarkedRouteIsUnchanged(): void
    {
        $response = $this->request('/other', self::NAVIGATE);

        self::assertSame(200, $response->getStatusCode());
        self::assertNotContains('Sec-Fetch-Mode', $response->getVary());
    }

    public function testAMarkedRouteWhoseOwningPageCannotBeGeneratedIsUnchanged(): void
    {
        $response = $this->request('/orphan', self::NAVIGATE);

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($response->headers->has('Location'));
    }

    public function testTheOwningPagePrintsTheSafeListAndTheRegeneratedTarget(): void
    {
        $location = (string) $this->request('/t/acme/import/42/mapping?tab=columns', self::NAVIGATE)->headers->get('Location');
        $page = (string) $this->request($location, self::NAVIGATE)->getContent();

        self::assertStringContainsString('data-nowo-ui-remote-form-reopen-param="_dialog"', $page);
        self::assertStringContainsString('data-nowo-ui-remote-form-reopen-routes="import_mapping import_upload"', $page);
        self::assertStringContainsString('data-nowo-ui-remote-form-reopen-route="import_mapping"', $page);
        self::assertStringContainsString('data-nowo-ui-remote-form-reopen-url="/t/acme/import/42/mapping?tab=columns"', $page);
        // The other dialog of the page has no marked route: unchanged markup.
        self::assertMatchesRegularExpression('/<dialog id="other-dialog"(?![^>]*reopen)[^>]*>/', $page);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function ignoredReopenQueries(): iterable
    {
        yield 'foreign route' => ['_dialog[route]=other'];
        yield 'unknown route' => ['_dialog[route]=nope'];
        yield 'route of another page' => ['_dialog[route]=orphan'];
        yield 'raw URL' => ['_dialog[route]=https://evil.example/'];
        yield 'parameter the route rejects' => ['_dialog[route]=import_mapping&_dialog[params][tenant]=acme&_dialog[params][id]=abc'];
        yield 'missing parameter' => ['_dialog[route]=import_mapping&_dialog[params][tenant]=acme'];
        yield 'nested parameter' => ['_dialog[route]=import_mapping&_dialog[params][tenant]=acme&_dialog[params][id]=1&_dialog[params][x][y]=z'];
        yield 'params not a list' => ['_dialog[route]=import_upload&_dialog[params]=x'];
        yield 'not an array' => ['_dialog=import_upload'];
    }

    #[DataProvider('ignoredReopenQueries')]
    public function testAReopenQueryWithAForeignOrUnknownRouteIsIgnored(string $query): void
    {
        $page = (string) $this->request('/t/acme/import?'.$query, self::NAVIGATE)->getContent();

        self::assertStringContainsString('data-nowo-ui-remote-form-reopen-routes="import_mapping import_upload"', $page);
        self::assertStringNotContainsString('data-nowo-ui-remote-form-reopen-route=', $page);
        self::assertStringNotContainsString('data-nowo-ui-remote-form-reopen-url', $page);
    }
}

/**
 * @internal
 */
final class DirectGetTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $dir)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new NowoUiKitBundle();
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function getCacheDir(): string
    {
        return $this->dir.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->dir.'/log';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', ['test' => true, 'secret' => 'test', 'http_method_override' => false, 'router' => ['utf8' => true]]);
        $container->loadFromExtension('twig', ['default_path' => '%kernel.project_dir%/templates']);
        // The denied cases log their 403 / 404: keep the test output quiet.
        $container->register('logger', NullLogger::class);
        // A kit prepends its routes, the host adds one: both reach the map.
        $container->prependExtensionConfig('nowo_ui_kit', ['remote_dialog' => ['direct_get' => ['routes' => [
            'import_mapping' => ['page' => 'import_page', 'dialog' => 'import-dialog', 'page_parameters' => ['tenant']],
        ]]]]);
        $container->loadFromExtension('nowo_ui_kit', ['remote_dialog' => ['direct_get' => ['routes' => [
            'import_upload' => ['page' => 'import_page', 'dialog' => 'import-dialog', 'page_parameters' => ['tenant'], 'redirect_same_origin' => true],
            'orphan' => ['page' => 'missing_page', 'dialog' => 'import-dialog'],
        ]]]]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('import_page', '/t/{tenant}/import')->controller('kernel::page')->methods(['GET']);
        $routes->add('import_upload', '/t/{tenant}/import/upload')->controller('kernel::upload')->methods(['GET']);
        $routes->add('import_mapping', '/t/{tenant}/import/{id}/mapping')->controller('kernel::mapping')->methods(['GET', 'POST'])->requirements(['id' => '\d+']);
        $routes->add('other', '/other')->controller('kernel::other');
        $routes->add('orphan', '/orphan')->controller('kernel::other');
    }

    public function page(Environment $twig): Response
    {
        return new Response($twig->render('@NowoUiKitBundle/partials/_remote_form_dialog.html.twig', ['id' => 'import-dialog'])
            .$twig->render('@NowoUiKitBundle/partials/_remote_form_dialog.html.twig', ['id' => 'other-dialog']));
    }

    public function upload(): Response
    {
        return new Response('<form data-step="upload"></form>');
    }

    public function mapping(Request $request, string $id): Response
    {
        // The controller's own access check runs first: a hidden import is a 404, a forbidden one a 403.
        if ('404' === $id) {
            throw new NotFoundHttpException();
        }
        if ('403' === $id) {
            throw new AccessDeniedHttpException();
        }
        if ('json' === $request->query->get('format')) {
            return new Response('{}', 200, ['Content-Type' => 'application/json']);
        }
        if ($request->isMethod('POST')) {
            return $request->request->has('ok')
                ? new Response('<p>saved</p>')
                : new Response(\sprintf('<form data-step="mapping-%s" data-invalid></form>', $id), 422);
        }
        $response = new Response(\sprintf('<form data-step="mapping-%s"></form>', $id));
        $response->headers->setCookie(Cookie::create('flow', 'kept'));

        return $response;
    }

    public function other(): Response
    {
        return new Response('<p>other</p>');
    }
}
