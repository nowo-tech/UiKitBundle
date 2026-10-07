<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\Enum\CssFramework;
use Nowo\UiKitBundle\Enum\IconSet;
use Nowo\UiKitBundle\Enum\RowActionsDisplay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertSame(CssFramework::Bootstrap5->value, $config['css_framework']);
        self::assertSame(IconSet::BootstrapIcons->value, $config['icon_set']);
        self::assertSame(RowActionsDisplay::Icon->value, $config['row_actions_display']);
        self::assertSame([], $config['panel_path_rewrites']);
    }

    public function testPanelPathRewritesMap(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'panel_path_rewrites' => ['/admin/blog' => '/panel/blog', '/settings/geo' => '/panel/geo'],
        ]]);

        self::assertSame(['/admin/blog' => '/panel/blog', '/settings/geo' => '/panel/geo'], $config['panel_path_rewrites']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidRewrites(): iterable
    {
        yield 'source without slash' => [['admin' => '/panel']];
        yield 'target without slash' => [['/admin' => 'panel']];
        yield 'identical' => [['/admin' => '/admin/']];
        yield 'nested target (loop)' => [['/admin' => '/admin/panel']];
        yield 'non scalar target' => [['/admin' => ['x']]];
    }

    /**
     * @param array<string, mixed> $map
     */
    #[DataProvider('invalidRewrites')]
    public function testRejectsInvalidPanelPathRewrites(array $map): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [['panel_path_rewrites' => $map]]);
    }

    public function testCustomAndSvgInline(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'css_framework' => 'custom',
            'icon_set' => 'svg_inline',
            'row_actions_display' => 'text',
        ]]);

        self::assertSame('custom', $config['css_framework']);
        self::assertSame('svg_inline', $config['icon_set']);
        self::assertSame('text', $config['row_actions_display']);
    }

    public function testRejectsUnknownRowActionsDisplay(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'row_actions_display' => 'labels_only',
        ]]);
    }

    public function testAcceptsAllRowActionsDisplayValues(): void
    {
        foreach (RowActionsDisplay::values() as $value) {
            $config = (new Processor())->processConfiguration(new Configuration(), [[
                'row_actions_display' => $value,
            ]]);
            self::assertSame($value, $config['row_actions_display']);
        }
    }

    public function testRejectsUnknownFramework(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'css_framework' => 'bulma',
        ]]);
    }

    public function testAcceptsAllFrameworkValues(): void
    {
        foreach (CssFramework::values() as $value) {
            $config = (new Processor())->processConfiguration(new Configuration(), [[
                'css_framework' => $value,
            ]]);
            self::assertSame($value, $config['css_framework']);
        }
    }
}
