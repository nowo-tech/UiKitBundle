<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\Enum\CssFramework;
use Nowo\UiKitBundle\Enum\IconSet;
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
    }

    public function testCustomAndSvgInline(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'css_framework' => 'custom',
            'icon_set' => 'svg_inline',
        ]]);

        self::assertSame('custom', $config['css_framework']);
        self::assertSame('svg_inline', $config['icon_set']);
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
