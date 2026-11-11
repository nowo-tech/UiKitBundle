<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Integration;

use Nowo\UiKitBundle\Dialog\RemoteDialogRedirectEvent;
use Nowo\UiKitBundle\Listing\ListingContext;
use Nowo\UiKitBundle\Listing\ListingCustomizerViewBuilder;
use Nowo\UiKitBundle\Listing\ListingPreferenceReaderInterface;
use Nowo\UiKitBundle\Listing\ListingViewerResolverInterface;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Nowo\UiKitBundle\Tests\Unit\Listing\InvoiceListing;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * 2.49, REQ-ARCH-001 DDD host: the kit booted with host adapters that use only the framework-free ports — a
 * {@see ListingPreferenceReaderInterface} keyed by viewer and project, a {@see ListingViewerResolverInterface}, and a
 * {@see RemoteDialogRedirectEvent} listener that reads scalars only. Requests go through `http_kernel` with no services
 * resetter (FrankenPHP worker, kernel reset false), two viewers in alternation: each sees only its own layout and
 * memory stays flat (REQ-RUNTIME-002).
 */
final class DddHostListingKernelTest extends TestCase
{
    private const REQUESTS = 400;

    private ?string $dir = null;

    protected function tearDown(): void
    {
        if (null !== $this->dir) {
            (new Filesystem())->remove($this->dir);
        }
    }

    public function testHostPortsWithoutRequestTypesAcrossManyRequests(): void
    {
        $this->dir = sys_get_temp_dir().'/nowo-ui-kit-ddd-host-'.bin2hex(random_bytes(4));
        $kernel = new DddHostTestKernel($this->dir);
        $kernel->boot();

        try {
            $httpKernel = $kernel->getContainer()->get('http_kernel');
            self::assertInstanceOf(HttpKernelInterface::class, $httpKernel);

            $expected = [
                'alice' => ['p1' => ['number', 'total'], 'p2' => ['number', 'customer', 'total']],
                'bob' => ['p1' => ['notes', 'number', 'customer', 'total'], 'p2' => ['number', 'customer', 'total']],
            ];
            $memoryAfterWarmUp = 0;
            for ($i = 0; $i < self::REQUESTS; ++$i) {
                $viewer = 0 === $i % 2 ? 'alice' : 'bob';
                $project = 0 === $i % 3 ? 'p2' : 'p1';
                $request = Request::create('https://app.example/p/'.$project.'/invoices');
                $request->headers->set('X-Test-Viewer', $viewer);
                $response = $httpKernel->handle($request);
                if ($httpKernel instanceof TerminableInterface) {
                    $httpKernel->terminate($request, $response);
                }
                self::assertSame(200, $response->getStatusCode(), 'request '.$i);
                $data = json_decode((string) $response->getContent(), true, 8, \JSON_THROW_ON_ERROR);
                self::assertSame($expected[$viewer][$project], $data['columns'], 'request '.$i.' ('.$viewer.', '.$project.')');
                self::assertNull($data['cookie'], 'server-side reader: no cookie');

                if (0 === $i % 10) {
                    $dialog = Request::create('https://app.example/p/'.$project.'/connect');
                    $dialog->headers->set('X-Nowo-Dialog', '1');
                    $answer = $httpKernel->handle($dialog);
                    self::assertSame(204, $answer->getStatusCode());
                    self::assertSame('/p/'.$project.'/board', $answer->headers->get('X-Nowo-Redirect'), 'the scalar-only listener retargeted the redirect');
                }
                if (49 === $i) {
                    gc_collect_cycles();
                    $memoryAfterWarmUp = memory_get_usage();
                }
            }
            gc_collect_cycles();

            self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $memoryAfterWarmUp, 'memory stays flat');

            // request-free entry point: a presenter builds the context itself
            $builder = $kernel->getContainer()->get('test.listing_builder');
            self::assertInstanceOf(ListingCustomizerViewBuilder::class, $builder);
            $view = $builder->buildForContext(InvoiceListing::KEY, new ListingContext([], 'alice', null, ['project' => 'p1']));
            self::assertSame(['number', 'total'], $view->layout->visibleColumnKeys());
            $anonymous = $builder->buildForContext(InvoiceListing::KEY, new ListingContext([], null, null, ['project' => 'p1']));
            self::assertFalse($anonymous->layout->stored, 'unknown viewer: the definition defaults');
            self::assertSame(['number', 'customer', 'total'], $anonymous->layout->visibleColumnKeys());
        } finally {
            $kernel->shutdown();
        }
    }
}

/**
 * Host reader: layouts per viewer and project, from a fixed map (stateless).
 *
 * @internal
 */
final readonly class HostListingReader implements ListingPreferenceReaderInterface
{
    private const LAYOUTS = [
        'alice|p1' => [['key' => 'customer', 'visible' => false]],
        'bob|p1' => [['key' => 'notes', 'visible' => true]],
    ];

    public function load(string $listingKey, ListingContext $context): ?array
    {
        if (null === $context->viewerId) {
            return null; // fail closed: never a shared row for an unknown viewer (audit MR-U-L2)
        }
        $project = $context->routeParameters['project'] ?? null;
        $columns = self::LAYOUTS[$context->viewerId.'|'.(\is_string($project) ? $project : '')] ?? null;

        return null === $columns ? null : ['columns' => $columns, 'filters' => null, 'mode' => null];
    }

    public function cookieName(string $listingKey): ?string
    {
        return null;
    }
}

/**
 * Host viewer adapter: resolves per call from the current request (no cached identity).
 *
 * @internal
 */
final readonly class HeaderViewerResolver implements ListingViewerResolverInterface
{
    public function __construct(private RequestStack $requests)
    {
    }

    public function viewerId(): ?string
    {
        return $this->requests->getMainRequest()?->headers->get('X-Test-Viewer');
    }
}

/**
 * Host listener that reads the redirect event through scalars only.
 *
 * @internal
 */
final class ScalarRedirectListener
{
    public function __invoke(RemoteDialogRedirectEvent $event): void
    {
        $project = $event->getRouteParameters()['project'] ?? null;
        if ('connect' === $event->getRequestRoute() && \is_string($project) && 302 === $event->getRedirectStatusCode()
            && str_ends_with($event->getRedirectTargetUrl(), '/done')) {
            $event->setAnswer(RemoteDialogRedirectEvent::ANSWER_REDIRECT);
            $event->setPath('/p/'.$project.'/board');
        }
    }
}

/**
 * @internal
 */
final class DddHostTestKernel extends Kernel
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
        $builder = $this->getContainer()->get('test.listing_builder');
        \assert($builder instanceof ListingCustomizerViewBuilder);
        ['view' => $view] = $builder->forRequest(InvoiceListing::KEY, $request);

        return new JsonResponse(['columns' => $view->layout->visibleColumnKeys(), 'cookie' => $view->cookieName]);
    }

    public function connect(string $project): RedirectResponse
    {
        return new RedirectResponse('/p/'.$project.'/done');
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
        ]);
        $container->loadFromExtension('nowo_ui_kit', ['remote_dialog' => ['step_routes' => ['connect']]]);
        $container->register(InvoiceListing::class, InvoiceListing::class)->setAutoconfigured(true);
        $container->register(HostListingReader::class, HostListingReader::class);
        $container->setAlias(ListingPreferenceReaderInterface::class, HostListingReader::class);
        $container->register(HeaderViewerResolver::class, HeaderViewerResolver::class)->setAutowired(true);
        $container->setAlias(ListingViewerResolverInterface::class, HeaderViewerResolver::class);
        $container->register(ScalarRedirectListener::class, ScalarRedirectListener::class)
            ->addTag('kernel.event_listener', ['event' => RemoteDialogRedirectEvent::class]);
        $container->setAlias('test.listing_builder', ListingCustomizerViewBuilder::class)->setPublic(true);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('invoices', '/p/{project}/invoices')->controller('kernel::invoices');
        $routes->add('connect', '/p/{project}/connect')->controller('kernel::connect');
    }
}
