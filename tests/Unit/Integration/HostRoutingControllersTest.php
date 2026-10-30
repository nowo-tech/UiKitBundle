<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Integration;

use Nowo\UiKitBundle\NowoUiKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

/**
 * REQ-SF-004 rule 7: the bundle routes are opt-in. A host whose routing only holds the Symfony default
 * `routing.controllers` resource must not get any bundle route (the release-status route exists only where the host
 * imports `@NowoUiKitBundle/Resources/config/routes/release_check.yaml`).
 */
final class HostRoutingControllersTest extends TestCase
{
    private ?string $dir = null;

    protected function tearDown(): void
    {
        if (null !== $this->dir) {
            (new Filesystem())->remove($this->dir);
        }
    }

    public function testRoutingControllersResourceDoesNotLoadTheBundleRoutes(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }

        $bundleRoutes = [];
        foreach ($this->routes(false)->all() as $name => $route) {
            $controller = $route->getDefault('_controller');
            if (str_starts_with($name, 'nowo_ui_kit') || (\is_string($controller) && (str_starts_with($controller, 'Nowo\\UiKitBundle\\') || str_starts_with($controller, 'nowo_ui_kit.')))) {
                $bundleRoutes[] = $name;
            }
        }

        self::assertSame([], $bundleRoutes, 'The bundle routes must only exist when the host imports them.');
    }

    public function testImportedReleaseCheckRouteIsRegistered(): void
    {
        self::assertSame('/_nowo-ui/release/status', $this->routes(true)->get('nowo_ui_kit_release_status')?->getPath());
    }

    private function routes(bool $importBundleRoutes): RouteCollection
    {
        $this->dir = sys_get_temp_dir().'/nowo-ui-kit-routing-'.bin2hex(random_bytes(4));
        $kernel = new HostRoutingTestKernel($this->dir, $importBundleRoutes);
        $kernel->boot();

        try {
            $router = $kernel->getContainer()->get('router');
            self::assertInstanceOf(RouterInterface::class, $router);

            return $router->getRouteCollection();
        } finally {
            $kernel->shutdown();
        }
    }
}

/**
 * Host kernel whose routing is the Symfony default `routing.controllers` resource, plus the documented import of the
 * bundle routes when `$importBundleRoutes` is true.
 *
 * @internal
 */
final class HostRoutingTestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $dir, private readonly bool $importBundleRoutes)
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
        $container->loadFromExtension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
        ]);
        $container->loadFromExtension('twig', ['default_path' => '%kernel.project_dir%/templates']);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('routing.controllers');
        if ($this->importBundleRoutes) {
            $routes->import('@NowoUiKitBundle/Resources/config/routes/release_check.yaml');
        }
    }
}
