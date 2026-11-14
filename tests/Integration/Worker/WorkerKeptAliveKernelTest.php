<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Integration\Worker;

use Nowo\UiKitBundle\Appearance\Appearance;
use Nowo\UiKitBundle\Appearance\AppearanceLogos;
use Nowo\UiKitBundle\Appearance\AppearanceProviderInterface;
use Nowo\UiKitBundle\Appearance\AppearanceView;
use Nowo\UiKitBundle\Appearance\ConfigAppearanceProvider;
use Nowo\UiKitBundle\Listing\CookieListingPreferenceStore;
use Nowo\UiKitBundle\Listing\ListingCustomizerViewBuilder;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Nowo\UiKitBundle\Tests\Unit\Listing\InvoiceListing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

/**
 * 2.49.1 (REQ-RUNTIME-002, batch C9): FrankenPHP worker mode with the kernel kept alive and NOT reset. One kernel,
 * 1,000 main requests through `http_kernel` (never `Kernel::handle()`, so the services resetter never runs), two users
 * of two brands in alternation on a standard host:
 *
 * - appearance: a host {@see AppearanceProviderInterface} resolves the brand per call; the kit's {@see AppearanceView}
 *   memo (per main request, weak reference) is asked once per request and never hands one brand's appearance, logos or
 *   `<head>` links to the other; a provider failure on one request does not stick to the next;
 * - listing: the default cookie store gives each user only their own column layout;
 * - memory grows less than 2 MiB after a 50-request warm-up.
 *
 * A second test reads the view outside any request (console command, Messenger handler): the memo of the last request
 * is never reused there.
 */
final class WorkerKeptAliveKernelTest extends TestCase
{
    private const REQUESTS = 1000;

    private ?string $dir = null;

    protected function tearDown(): void
    {
        if (null !== $this->dir) {
            (new Filesystem())->remove($this->dir);
        }
    }

    public function testTwoBrandsAndTwoUsersAlternateWithoutLeaking(): void
    {
        $kernel = $this->bootKernel();

        try {
            $container = $kernel->getContainer();
            $httpKernel = $container->get('http_kernel');
            self::assertInstanceOf(HttpKernelInterface::class, $httpKernel);
            $provider = $container->get('test.brand_provider');
            self::assertInstanceOf(HeaderBrandAppearanceProvider::class, $provider);

            $users = [
                'alice' => ['brand' => 'acme', 'columns' => ['number', 'total'], 'cookie' => [['key' => 'customer', 'visible' => false]]],
                'bob' => ['brand' => 'globex', 'columns' => ['notes', 'number', 'customer', 'total'], 'cookie' => [['key' => 'notes', 'visible' => true]]],
            ];
            $failures = 0;
            $memoryAfterWarmUp = 0;
            for ($i = 0; $i < self::REQUESTS; ++$i) {
                $user = 0 === $i % 2 ? 'alice' : 'bob';
                $brand = $users[$user]['brand'];
                // requests 1, 51, 101, … (bob, globex): the host storage is down for that request only
                $fail = 'globex' === $brand && 1 === $i % 50;
                $request = Request::create('https://app.example/invoices?n='.$i);
                $request->headers->set('X-Test-Brand', $fail ? 'down' : $brand);
                $request->cookies->set(
                    CookieListingPreferenceStore::name(InvoiceListing::KEY),
                    json_encode(['v' => 1, 'columns' => $users[$user]['cookie']], \JSON_THROW_ON_ERROR),
                );
                $callsBefore = $provider->calls;

                $response = $httpKernel->handle($request);
                if ($httpKernel instanceof TerminableInterface) {
                    $httpKernel->terminate($request, $response);
                }

                self::assertSame(200, $response->getStatusCode(), 'request '.$i);
                $data = json_decode((string) $response->getContent(), true, 8, \JSON_THROW_ON_ERROR);
                self::assertIsArray($data);
                self::assertSame(1, $provider->calls - $callsBefore, 'request '.$i.': the provider is asked once per request (memo shared by the templates, never across requests)');
                self::assertSame($users[$user]['columns'], $data['columns'], 'request '.$i.' ('.$user.'): own layout only');
                if ($fail) {
                    ++$failures;
                    self::assertSame('none|Defaults Inc', $data['brand'], 'request '.$i.': a failing provider falls back for this request');
                    self::assertSame('', $data['head'], 'request '.$i.': no head links without an appearance');
                } else {
                    $other = 'acme' === $brand ? 'globex' : 'acme';
                    self::assertSame(ucfirst($brand).'|'.ucfirst($brand), $data['brand'], 'request '.$i.' ('.$user.')');
                    self::assertStringContainsString('href="/'.$brand.'.ico"', $data['head'], 'request '.$i);
                    self::assertStringNotContainsString($other, $data['head'], 'request '.$i.': no other brand in <head>');
                }
                if (49 === $i) {
                    gc_collect_cycles();
                    $memoryAfterWarmUp = memory_get_usage();
                }
            }
            gc_collect_cycles();

            self::assertSame(self::REQUESTS, $provider->calls);
            self::assertSame(20, $failures);
            self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $memoryAfterWarmUp, 'memory stays flat');
        } finally {
            $kernel->shutdown();
        }
    }

    public function testOutsideARequestTheLastRequestMemoIsNeverReused(): void
    {
        $kernel = $this->bootKernel();

        try {
            $container = $kernel->getContainer();
            $httpKernel = $container->get('http_kernel');
            self::assertInstanceOf(HttpKernelInterface::class, $httpKernel);
            $provider = $container->get('test.brand_provider');
            self::assertInstanceOf(HeaderBrandAppearanceProvider::class, $provider);
            $view = $container->get('test.appearance_view');
            self::assertInstanceOf(AppearanceView::class, $view);

            for ($i = 0; $i < 20; ++$i) {
                $brand = 0 === $i % 2 ? 'acme' : 'globex';
                $request = Request::create('https://app.example/invoices');
                $request->headers->set('X-Test-Brand', $brand);
                $response = $httpKernel->handle($request);
                if ($httpKernel instanceof TerminableInterface) {
                    $httpKernel->terminate($request, $response);
                }
                self::assertStringContainsString(ucfirst($brand).'|', (string) $response->getContent());

                // a console command or a Messenger handler right after the request: no main request on the stack
                $provider->outsideRequestBrand = 'acme' === $brand ? 'globex' : 'acme';
                self::assertSame(ucfirst($provider->outsideRequestBrand), $view->current()?->brandName, 'run '.$i.': never the memo of the last request');
                self::assertStringContainsString('/'.$provider->outsideRequestBrand.'.ico', $view->head());
            }
        } finally {
            $kernel->shutdown();
        }
    }

    private function bootKernel(): WorkerKeptAliveTestKernel
    {
        $this->dir = sys_get_temp_dir().'/nowo-ui-kit-worker-'.bin2hex(random_bytes(4));
        (new Filesystem())->dumpFile(
            $this->dir.'/templates/brand.html.twig',
            "{{ nowo_ui_appearance_or_null().brandName|default('none') }}|{{ nowo_ui_appearance().brandName }}",
        );
        (new Filesystem())->dumpFile($this->dir.'/templates/head.html.twig', '{{ nowo_ui_appearance_head() }}');
        $kernel = new WorkerKeptAliveTestKernel($this->dir);
        $kernel->boot();

        return $kernel;
    }
}

/**
 * Host appearance adapter: resolves the brand per call from the current main request (header), or from
 * {@see self::$outsideRequestBrand} outside a request. Brand "down" throws (host storage unavailable).
 *
 * @internal
 */
final class HeaderBrandAppearanceProvider implements AppearanceProviderInterface
{
    public int $calls = 0;

    public string $outsideRequestBrand = 'acme';

    public function __construct(private readonly ConfigAppearanceProvider $defaults, private readonly RequestStack $requests)
    {
    }

    public function getAppearance(): Appearance
    {
        ++$this->calls;
        $brand = $this->requests->getMainRequest()?->headers->get('X-Test-Brand') ?? $this->outsideRequestBrand;
        if ('down' === $brand) {
            throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused');
        }

        return $this->defaults->getAppearance()->with([
            'brandName' => ucfirst($brand),
            'logos' => new AppearanceLogos(lightSrc: '/'.$brand.'.svg', faviconSrc: '/'.$brand.'.ico'),
        ]);
    }
}

/**
 * @internal
 */
final class WorkerKeptAliveTestKernel extends Kernel
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

    public function invoices(Request $request): JsonResponse
    {
        $container = $this->getContainer()->get('test.service_container');
        $twig = $container->get('twig');
        \assert($twig instanceof Environment);
        $builder = $this->getContainer()->get('test.listing_builder');
        \assert($builder instanceof ListingCustomizerViewBuilder);
        ['view' => $view] = $builder->forRequest(InvoiceListing::KEY, $request);

        // two templates read the appearance: one provider call per request (the main-request memo)
        return new JsonResponse([
            'brand' => trim($twig->render('brand.html.twig')),
            'head' => trim($twig->render('head.html.twig')),
            'columns' => $view->layout->visibleColumnKeys(),
        ]);
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
        ]);
        $container->loadFromExtension('twig', ['default_path' => '%kernel.project_dir%/templates']);
        $container->loadFromExtension('nowo_ui_kit', ['appearance' => [
            'enabled' => true,
            'provider' => 'test.brand_provider',
            'head_links' => true,
            'defaults' => ['brand_name' => 'Defaults Inc'],
        ]]);
        $container->register('test.brand_provider', HeaderBrandAppearanceProvider::class)
            ->setArguments([new Reference('nowo_ui_kit.appearance.config_provider'), new Reference('request_stack')])
            ->setPublic(true);
        $container->register(InvoiceListing::class, InvoiceListing::class)->setAutoconfigured(true);
        $container->setAlias('test.appearance_view', 'nowo_ui_kit.appearance.view')->setPublic(true);
        $container->setAlias('test.listing_builder', ListingCustomizerViewBuilder::class)->setPublic(true);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('invoices', '/invoices')->controller('kernel::invoices');
    }
}
