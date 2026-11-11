<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Integration;

use Nowo\UiKitBundle\NowoUiKitBundle;
use Nowo\UiKitBundle\Twig\ErrorPageExtension;
use Nowo\UiKitBundle\View\ErrorIllustration;
use Nowo\UiKitBundle\View\ErrorIllustrationProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * 2.47, FrankenPHP worker mode with the kernel kept alive and NOT reset: N error pages through `http_kernel` (no
 * `Kernel::handle()`, so no services resetter) with an illustration provider that answers, returns null, throws or
 * runs over budget. Each request renders its own fallback, an over-budget request never makes a later one skip the
 * provider, and memory stays flat.
 */
final class ErrorIllustrationWorkerKernelTest extends TestCase
{
    private const REQUESTS = 400;

    private ?string $dir = null;

    protected function tearDown(): void
    {
        if (null !== $this->dir) {
            (new Filesystem())->remove($this->dir);
        }
    }

    public function testManyRequestsWithoutResetKeepNoState(): void
    {
        $this->dir = sys_get_temp_dir().'/nowo-ui-kit-error-illus-'.bin2hex(random_bytes(4));
        (new Filesystem())->dumpFile($this->dir.'/templates/bundles/TwigBundle/Exception/error.html.twig', "{% extends '@NowoUiKit/error/status.html.twig' %}");
        $kernel = new ErrorIllustrationTestKernel($this->dir);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            $httpKernel = $container->get('http_kernel');
            self::assertInstanceOf(HttpKernelInterface::class, $httpKernel);
            $provider = $container->get('test.error_illustration_provider');
            self::assertInstanceOf(WorkerIllustrationProvider::class, $provider);
            $logger = $container->get('logger');
            self::assertInstanceOf(CountingLogger::class, $logger);

            $expected = [
                404 => 'src="/brand/404.png"',
                503 => 'src="/bundles/nowouikit/illustrations/error.png"',
                500 => 'src="/bundles/nowouikit/illustrations/error-500.png"',
                410 => 'src="/brand/410.png"',
            ];
            $memoryAfterWarmUp = 0;
            for ($i = 0; $i < self::REQUESTS; ++$i) {
                // the slow 410 runs a few times only (it sleeps over the budget)
                $code = 0 === $i % 100 ? 410 : [404, 503, 500][$i % 3];
                $request = Request::create('https://app.example/fail/'.$code.'?n='.$i);
                $response = $httpKernel->handle($request);
                if ($httpKernel instanceof TerminableInterface) {
                    $httpKernel->terminate($request, $response);
                }

                self::assertSame($code, $response->getStatusCode(), 'request '.$i);
                self::assertStringContainsString($expected[$code], (string) $response->getContent(), 'request '.$i.' ('.$code.')');
                self::assertFalse($request->attributes->has(ErrorPageExtension::PROVIDER_SKIP_ATTRIBUTE), 'the main request never carries the flag');
                if (49 === $i) {
                    gc_collect_cycles();
                    $memoryAfterWarmUp = memory_get_usage();
                }
            }
            gc_collect_cycles();

            self::assertSame(self::REQUESTS, $provider->calls, 'every request asked the provider: no skip leaked across requests');
            self::assertSame(4, $provider->slowCalls);
            self::assertSame(['exceptions' => 132, 'budget' => 4], $logger->illustrationWarnings);
            self::assertLessThan(2 * 1024 * 1024, memory_get_usage() - $memoryAfterWarmUp, 'memory stays flat');
        } finally {
            $kernel->shutdown();
        }
    }
}

/**
 * 404 and 410 → a provided URL (410 sleeps over the budget), 503 → null (map default), 500 → throws (map 500).
 *
 * @internal
 */
final class WorkerIllustrationProvider implements ErrorIllustrationProviderInterface
{
    public int $calls = 0;

    public int $slowCalls = 0;

    public function getIllustration(int $statusCode): ?ErrorIllustration
    {
        ++$this->calls;
        if (410 === $statusCode) {
            ++$this->slowCalls;
            usleep(30_000);
        }

        return match ($statusCode) {
            404, 410 => ErrorIllustration::fromUrl('/brand/'.$statusCode.'.png', 640, 480),
            500 => throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused'),
            default => null,
        };
    }
}

/**
 * Counts the illustration warnings without keeping the records (memory stays flat).
 *
 * @internal
 */
final class CountingLogger extends AbstractLogger
{
    /** @var array{exceptions: int, budget: int} */
    public array $illustrationWarnings = ['exceptions' => 0, 'budget' => 0];

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $message = (string) $message;
        if (str_contains($message, 'illustration provider failed')) {
            ++$this->illustrationWarnings['exceptions'];
        } elseif (str_contains($message, 'illustration provider exceeded')) {
            ++$this->illustrationWarnings['budget'];
        }
    }
}

/**
 * @internal
 */
final class ErrorIllustrationTestKernel extends Kernel implements CompilerPassInterface
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

    public function fail(int $code): never
    {
        throw new HttpException($code, 'boom');
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'assets' => ['base_path' => '/bundles/nowouikit'],
        ]);
        $container->loadFromExtension('twig', ['default_path' => '%kernel.project_dir%/templates']);
        $container->loadFromExtension('nowo_ui_kit', ['error_pages' => [
            'illustration_provider' => 'test.error_illustration_provider',
            'illustration_provider_budget_ms' => 15,
            'illustrations' => [500 => 'illustrations/error-500', 'default' => 'illustrations/error'],
            'webp' => false,
        ]]);
        $container->register('test.error_illustration_provider', WorkerIllustrationProvider::class)->setPublic(true);
        $container->register('logger', CountingLogger::class)->setPublic(true);
    }

    /**
     * A CLI test process picks the CLI error renderer; render the HTML (Twig) error page as a web worker does.
     */
    public function process(ContainerBuilder $container): void
    {
        $container->setAlias('error_renderer', 'error_renderer.html');
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('fail', '/fail/{code}')->controller('kernel::fail')->requirements(['code' => '\d+']);
    }
}
