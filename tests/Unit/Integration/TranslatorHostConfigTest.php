<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Integration;

use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\NowoUiKitBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The bundle must not override the host translator settings (enabled flag, fallbacks)
 * while its NowoUiKitBundle catalogue keeps working.
 */
final class TranslatorHostConfigTest extends TestCase
{
    private ?string $dir = null;

    protected function tearDown(): void
    {
        if (null !== $this->dir) {
            (new Filesystem())->remove($this->dir);
        }
    }

    public function testPrependDoesNotTouchFrameworkTranslator(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'framework';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });

        (new NowoUiKitExtension())->prepend($container);

        foreach ($container->getExtensionConfig('framework') as $config) {
            self::assertArrayNotHasKey('translator', $config);
        }
    }

    public function testHostFallbacksAreKeptAndBundleCatalogueLoads(): void
    {
        $container = $this->boot(['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['es']]);

        /** @var TranslatorInterface&\Symfony\Component\Translation\Translator $translator */
        $translator = $container->get('translator');
        self::assertSame(['es'], $translator->getFallbackLocales());
        self::assertSame('Bearbeiten', $translator->trans('action.edit', [], 'NowoUiKitBundle', 'de'));
        // `ja` has no catalogue: the host fallback (es), not a forced `en`, is used.
        self::assertSame(
            $translator->trans('action.edit', [], 'NowoUiKitBundle', 'es'),
            $translator->trans('action.edit', [], 'NowoUiKitBundle', 'ja'),
        );
        self::assertNotSame('Edit', $translator->trans('action.edit', [], 'NowoUiKitBundle', 'ja'));
    }

    public function testBootsWithTranslatorDisabled(): void
    {
        $container = $this->boot(['enabled' => false]);

        self::assertFalse($container->has('translator.default'));

        /** @var \Twig\Environment $twig */
        $twig = $container->get('twig');
        self::assertSame('action.edit', $twig->createTemplate("{{ 'action.edit'|trans({}, 'NowoUiKitBundle') }}")->render());
    }

    /**
     * @param array<string, mixed> $translator
     */
    private function boot(array $translator): \Symfony\Component\DependencyInjection\ContainerInterface
    {
        $this->dir = sys_get_temp_dir().'/nowo-ui-kit-translator-'.bin2hex(random_bytes(4));
        $kernel = new TranslatorTestKernel($this->dir, $translator);
        $kernel->boot();

        return $kernel->getContainer()->get('test.service_container');
    }
}

/**
 * @internal
 */
final class TranslatorTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $translator
     */
    public function __construct(private readonly string $dir, private readonly array $translator)
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
            'translator' => $this->translator,
        ]);
        $container->loadFromExtension('twig', ['default_path' => '%kernel.project_dir%/templates']);
    }
}
