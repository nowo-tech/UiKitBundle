<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Integration;

use Nowo\UiKitBundle\NowoUiKitBundle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The maps reach the templates in a booted kernel, whatever the bundle order (a Twig global built from a parameter
 * during TwigExtension load would miss them with TwigBundle registered first), and bad values fail the container build.
 */
final class ActionIconsKernelTest extends TestCase
{
    private ?string $dir = null;

    protected function tearDown(): void
    {
        if (null !== $this->dir) {
            (new Filesystem())->remove($this->dir);
        }
    }

    /**
     * @return iterable<string, array{0: bool}>
     */
    public static function bundleOrders(): iterable
    {
        yield 'TwigBundle first' => [true];
        yield 'UiKit first' => [false];
    }

    #[DataProvider('bundleOrders')]
    public function testMapsReachIconAndRowActions(bool $twigFirst): void
    {
        $twig = $this->twig($twigFirst, ['icon_set' => 'ux_icon', 'action_icons' => ['edit' => 'tabler:pencil', 'delete' => 'tabler:trash'], 'action_variants' => ['create' => 'secondary']]);

        self::assertStringContainsString('name="tabler:pencil"', $twig->render('@NowoUiKitBundle/components/_icon.html.twig', ['name' => 'edit']));
        self::assertStringContainsString('name="bi:eye"', $twig->render('@NowoUiKitBundle/components/_icon.html.twig', ['name' => 'view']));
        $row = $twig->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => [['kind' => 'delete', 'href' => '/d'], ['kind' => 'create', 'href' => '/c']]]);
        self::assertStringContainsString('name="tabler:trash"', $row);
        self::assertStringNotContainsString('btn-primary', $row);
    }

    #[DataProvider('bundleOrders')]
    public function testNoConfigKeepsTheKitDefaults(bool $twigFirst): void
    {
        $twig = $this->twig($twigFirst, ['icon_set' => 'ux_icon']);

        self::assertStringContainsString('name="bi:pencil"', $twig->render('@NowoUiKitBundle/components/_icon.html.twig', ['name' => 'edit']));
        self::assertStringContainsString('btn-primary', $twig->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => [['kind' => 'create', 'href' => '/c']]]));
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'markup in a name' => [['action_icons' => ['edit' => 'x" onclick="y']], 'action_icons'];
        yield 'two prefixes' => [['action_icons' => ['edit' => 'a:b:c']], 'action_icons'];
        yield 'upper case' => [['action_icons' => ['edit' => 'Tabler:Pencil']], 'action_icons'];
        yield 'class list' => [['action_icons' => ['edit' => 'ti ti-pencil']], 'action_icons'];
        yield 'empty name' => [['action_icons' => ['edit' => '']], 'action_icons'];
        yield 'unknown kind' => [['action_icons' => ['edti' => 'tabler:pencil']], 'unknown kind "edti"'];
        yield 'unknown variant' => [['action_variants' => ['edit' => 'neutral']], 'action_variants'];
        yield 'delete without danger' => [['action_variants' => ['delete' => 'secondary']], 'delete'];
        yield 'delete as primary' => [['action_variants' => ['delete' => 'primary']], 'delete'];
        yield 'unknown variant kind' => [['action_variants' => ['edti' => 'primary']], 'unknown kind "edti"'];
    }

    public function testDeleteMayBeMappedToDanger(): void
    {
        $twig = $this->twig(true, ['action_variants' => ['delete' => 'danger']]);

        self::assertStringContainsString('btn-outline-danger', $twig->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => [['kind' => 'delete', 'href' => '/d']]]));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalidConfigs')]
    public function testInvalidValuesAreRefusedAtBuild(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->twig(true, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function twig(bool $twigFirst, array $config): Environment
    {
        $this->dir = sys_get_temp_dir().'/nowo-ui-kit-action-icons-'.bin2hex(random_bytes(4));
        $kernel = new ActionIconsTestKernel($this->dir, $twigFirst, $config);
        $kernel->boot();
        $twig = $kernel->getContainer()->get('test.service_container')->get('twig');
        self::assertInstanceOf(Environment::class, $twig);

        return $twig;
    }
}

/**
 * @internal
 */
final class StubUxIconExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [new TwigFunction('ux_icon', static fn (string $name): string => '<ux-icon name="'.$name.'"></ux-icon>', ['is_safe' => ['html']])];
    }
}

/**
 * @internal
 */
final class ActionIconsTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly string $dir, private readonly bool $twigFirst, private readonly array $config)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        if ($this->twigFirst) {
            yield new TwigBundle();
            yield new FrameworkBundle();
            yield new NowoUiKitBundle();

            return;
        }
        yield new NowoUiKitBundle();
        yield new FrameworkBundle();
        yield new TwigBundle();
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
        $container->loadFromExtension('framework', ['test' => true, 'secret' => 'test', 'http_method_override' => false, 'translator' => ['default_path' => '%kernel.project_dir%/translations']]);
        $container->loadFromExtension('twig', ['default_path' => '%kernel.project_dir%/templates']);
        $container->loadFromExtension('nowo_ui_kit', $this->config);
        $container->register('test.stub_ux_icon', StubUxIconExtension::class)->addTag('twig.extension');
    }
}
