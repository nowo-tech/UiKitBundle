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
        self::assertSame('inline', $config['row_actions_confirm']);
        self::assertSame([], $config['panel_path_rewrites']);
        self::assertSame([
            'enabled' => false,
            'github_repo' => '',
            'current_version' => '',
            'cache_ttl' => 43200,
            'default_branch' => 'main',
            'user_agent' => 'nowo-ui-kit-release-check',
            'timeout' => 5.0,
            'releases_timeout' => 8.0,
        ], $config['release_check']);
    }

    public function testReleaseCheckTimeoutsAreConfigurableAndBounded(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'release_check' => ['timeout' => 2.5, 'releases_timeout' => 3],
        ]]);
        self::assertSame(2.5, $config['release_check']['timeout']);
        self::assertEquals(3.0, $config['release_check']['releases_timeout']);

        foreach ([['timeout' => 0], ['timeout' => 61], ['releases_timeout' => 0.0]] as $invalid) {
            try {
                (new Processor())->processConfiguration(new Configuration(), [['release_check' => $invalid]]);
                self::fail('Expected an invalid configuration for '.json_encode($invalid));
            } catch (InvalidConfigurationException $e) {
                self::assertStringContainsString('release_check', $e->getMessage());
            }
        }
    }

    public function testReleaseCheckOverridesAndTtlFloor(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'release_check' => ['enabled' => true, 'github_repo' => 'acme/app', 'current_version' => 'v1.2.3', 'cache_ttl' => 600],
        ]]);
        self::assertTrue($config['release_check']['enabled']);
        self::assertSame('acme/app', $config['release_check']['github_repo']);
        self::assertSame(600, $config['release_check']['cache_ttl']);

        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [['release_check' => ['cache_ttl' => 10]]]);
    }

    public function testRowActionsConfirmModes(): void
    {
        foreach (['inline', 'native', 'dialog'] as $mode) {
            $config = (new Processor())->processConfiguration(new Configuration(), [['row_actions_confirm' => $mode]]);
            self::assertSame($mode, $config['row_actions_confirm']);
        }

        $this->expectException(InvalidConfigurationException::class);
        (new Processor())->processConfiguration(new Configuration(), [['row_actions_confirm' => 'popup']]);
    }

    /** 2.51: UiKit's opt-in default theme (css/nowo-ui-base.css) is off unless the host turns it on. */
    public function testBaseStylesheetDefaultsToOff(): void
    {
        self::assertFalse((new Processor())->processConfiguration(new Configuration(), [[]])['base_stylesheet']);
        self::assertTrue((new Processor())->processConfiguration(new Configuration(), [['base_stylesheet' => true]])['base_stylesheet']);
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
        yield 'protocol-relative target' => [['/admin' => '//evil.com']];
        yield 'protocol-relative source' => [['//admin' => '/panel']];
        yield 'backslash target' => [['/admin' => '/\\evil.com']];
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

    public function testRootTargetIsAllowed(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [['panel_path_rewrites' => ['/admin' => '/']]]);

        self::assertSame(['/admin' => '/'], $config['panel_path_rewrites']);
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
