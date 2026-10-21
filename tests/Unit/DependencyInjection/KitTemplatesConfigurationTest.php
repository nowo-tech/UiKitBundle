<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Twig\ErrorPageExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * 2.6 configuration: nowo_ui_kit.error_pages and nowo_ui_kit.shell.
 */
final class KitTemplatesConfigurationTest extends TestCase
{
    public function testDefaultsMatchTheExtensionDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame(ErrorPageExtension::defaults(), $config['error_pages']);
        self::assertSame(['kit_styles' => true, 'sidebar_cookie' => null, 'sidebar_hidden_value' => 'collapsed', 'sidebar_rail_value' => 'rail', 'sidebar_storage' => null], $config['shell']);
    }

    public function testFullErrorPagesAndShellConfig(): void
    {
        $config = $this->process([
            'error_pages' => [
                'layout_template' => 'error/layout.html.twig',
                'translation_domain' => 'messages',
                'translation_prefix' => 'error',
                'illustrations' => [
                    404 => 'illustrations/error-404',
                    '503' => ['path' => 'illustrations/error-503', 'width' => 1536, 'height' => 1024, 'webp' => false],
                    'default' => 'illustrations/error',
                ],
                'illustration_width' => 896,
                'illustration_height' => 597,
                'retry_statuses' => [408, 425],
                'back_link_statuses' => [404, 410],
                'auto_retry_seconds' => 0,
                'theme_storage' => 'beacon-theme',
                'theme_toggle' => false,
                'html_attributes' => ['data-csp-strict-nonce' => true],
            ],
            'shell' => ['kit_styles' => false, 'sidebar_cookie' => 'beacon_sidebar', 'sidebar_storage' => 'beacon-sidebar'],
        ]);

        $errors = $config['error_pages'];
        self::assertSame(['path' => 'illustrations/error-404', 'width' => null, 'height' => null, 'webp' => null, 'extension' => null], $errors['illustrations'][404]);
        self::assertSame(['path' => 'illustrations/error-503', 'width' => 1536, 'height' => 1024, 'webp' => false, 'extension' => null], $errors['illustrations']['503']);
        self::assertSame('illustrations/error', $errors['illustrations']['default']['path']);
        self::assertSame([408, 425], $errors['retry_statuses']);
        self::assertSame(['data-csp-strict-nonce' => true], $errors['html_attributes']);
        self::assertSame('beacon_sidebar', $config['shell']['sidebar_cookie']);

        $page = (new ErrorPageExtension($errors))->page(503);
        self::assertSame([1536, 1024, false], [$page->illustration?->width, $page->illustration?->height, $page->illustration?->webp]);
        self::assertSame([896, 597, true], [(new ErrorPageExtension($errors))->page(404)->illustration?->width, (new ErrorPageExtension($errors))->page(404)->illustration?->height, (new ErrorPageExtension($errors))->page(404)->illustration?->webp]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'illustration key not a status' => [['error_pages' => ['illustrations' => ['oops' => 'a']]], 'illustrations key "oops"'];
        yield 'illustration key out of range' => [['error_pages' => ['illustrations' => [302 => 'a']]], 'illustrations key "302"'];
        yield 'on* html attribute' => [['error_pages' => ['html_attributes' => ['onload' => 'x']]], 'inline event handler'];
        yield 'array html attribute value' => [['error_pages' => ['html_attributes' => ['data-x' => ['a']]]], 'values are strings'];
        yield 'negative auto retry' => [['error_pages' => ['auto_retry_seconds' => -1]], 'auto_retry_seconds'];
        yield 'empty layout' => [['error_pages' => ['layout_template' => '']], 'layout_template'];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('invalid')]
    public function testInvalidConfig(array $config, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        $this->process($config);
    }

    public function testExtensionRegistersTheErrorPageTwigExtensionAndTheShellGlobal(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['error_pages' => ['translation_domain' => 'messages']]], $container);
        $definition = $container->getDefinition('nowo_ui_kit.error_page_twig_extension');
        self::assertSame(ErrorPageExtension::class, $definition->getClass());
        self::assertTrue($definition->hasTag('twig.extension'));
        self::assertSame('messages', $definition->getArgument(0)['translation_domain']);
        self::assertSame('messages', $container->getParameter('nowo_ui_kit.error_pages')['translation_domain']);

        $prepend = new ContainerBuilder();
        $prepend->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'twig';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $prepend->prependExtensionConfig(Configuration::ALIAS, ['shell' => ['sidebar_cookie' => 'sms_bridge_sidebar']]);
        (new NowoUiKitExtension())->prepend($prepend);
        $globals = $prepend->getExtensionConfig('twig')[0]['globals'] ?? [];
        self::assertSame('sms_bridge_sidebar', $globals['nowo_ui_kit_shell']['sidebar_cookie'] ?? null);
        self::assertTrue($globals['nowo_ui_kit_shell']['kit_styles'] ?? null);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
