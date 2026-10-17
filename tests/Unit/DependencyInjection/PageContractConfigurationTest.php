<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Theme\Theme;
use Nowo\UiKitBundle\Twig\ThemeExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PageContractConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = $this->process([]);

        self::assertSame(['admin' => '@NowoUiKit/layout/admin.html.twig', 'guest' => '@NowoUiKit/layout/guest.html.twig'], $config['layouts']);
        self::assertSame(['breadcrumb_kit' => true, 'collection' => null], $config['breadcrumbs']);
        self::assertSame(['framework_defaults' => true, 'classes' => [], 'attributes' => [], 'partials' => [], 'tokens' => [], 'tokens_dark' => [], 'bundles' => [], 'pages' => []], $config['theme']);
    }

    public function testFullThemeConfig(): void
    {
        $config = $this->process([
            'layouts' => ['admin' => 'layouts/admin.html.twig', 'guest' => 'layouts/guest.html.twig'],
            'breadcrumbs' => ['breadcrumb_kit' => false, 'collection' => 'admin'],
            'theme' => [
                'classes' => ['page_header' => 'app-page-header', 'btn_primary' => ['btn', 'btn-moss'], 'badge_success' => 'badge badge--ok'],
                'attributes' => ['toasts' => ['data-controller' => 'toast-stack', 'data-flag' => true]],
                'partials' => ['pagination' => 'kit/_pagination.html.twig'],
                'tokens' => ['primary' => 'var(--color-moss)', 'radius' => 4],
                'tokens_dark' => ['--nowo-ui-primary' => '#9c9'],
                'bundles' => [
                    'NowoHttpLogBundle' => ['classes' => ['table' => 'data-table data-table--dense'], 'tokens' => ['radius' => '0']],
                ],
            ],
        ]);

        self::assertSame('layouts/admin.html.twig', $config['layouts']['admin']);
        self::assertFalse($config['breadcrumbs']['breadcrumb_kit']);
        self::assertSame('btn btn-moss', $config['theme']['classes']['btn_primary']);
        self::assertSame(['data-controller' => 'toast-stack', 'data-flag' => true], $config['theme']['attributes']['toasts']);
        self::assertSame('4', $config['theme']['tokens']['radius']);
        self::assertSame('data-table data-table--dense', $config['theme']['bundles']['NowoHttpLogBundle']['classes']['table']);
        self::assertSame([], $config['theme']['bundles']['NowoHttpLogBundle']['partials']);

        $theme = Theme::fromConfig('bootstrap5', $config['theme']);
        self::assertStringContainsString('data-table--dense', $theme->classFor('table', 'NowoHttpLogBundle'));
    }

    public function testFrameworkDefaultsConfig(): void
    {
        $config = $this->process([
            'theme' => [
                'framework_defaults' => false,
                'classes' => [
                    'badge_success' => ['value' => 'acme-badge acme-badge--ok', 'framework_defaults' => false],
                    'btn_primary' => ['value' => ['acme-btn', 'acme-btn--primary']],
                    'table' => ['framework_defaults' => true],
                    'card' => ['acme-card'],
                ],
                'bundles' => [
                    'NowoHttpLogBundle' => ['framework_defaults' => true, 'classes' => ['table' => ['value' => 'dense', 'framework_defaults' => false]]],
                    'NowoXBundle' => [],
                ],
            ],
        ]);

        self::assertFalse($config['theme']['framework_defaults']);
        self::assertSame(['value' => 'acme-badge acme-badge--ok', 'framework_defaults' => false], $config['theme']['classes']['badge_success']);
        self::assertSame('acme-btn acme-btn--primary', $config['theme']['classes']['btn_primary']);
        self::assertSame(['value' => '', 'framework_defaults' => true], $config['theme']['classes']['table']);
        self::assertSame('acme-card', $config['theme']['classes']['card']);
        self::assertTrue($config['theme']['bundles']['NowoHttpLogBundle']['framework_defaults']);
        self::assertArrayNotHasKey('framework_defaults', $config['theme']['bundles']['NowoXBundle']);

        $theme = Theme::fromConfig('bootstrap5', $config['theme']);
        self::assertSame('nowo-ui-badge nowo-ui-badge--success acme-badge acme-badge--ok', $theme->classFor('badge_success'));
        self::assertSame('nowo-ui-btn nowo-ui-btn--primary acme-btn acme-btn--primary', $theme->classFor('btn_primary'));
        self::assertSame('nowo-ui-table nowo-ui-table--striped table table-hover align-middle', $theme->classFor('table'));
        self::assertSame('nowo-ui-table nowo-ui-table--striped dense', $theme->classFor('table', 'NowoHttpLogBundle'));
        self::assertSame('nowo-ui-card card acme-card', $theme->classFor('card', 'NowoHttpLogBundle'));
        self::assertSame('nowo-ui-card acme-card', $theme->classFor('card', 'NowoXBundle'));
    }

    public function testPageTypeVariationConfig(): void
    {
        $config = $this->process([
            'theme' => [
                'classes' => ['table' => 'host-table', 'card' => 'host-card'],
                'attributes' => ['table' => ['data-host' => '1']],
                'pages' => [
                    'list' => ['classes' => ['table' => 'list-table', 'card' => ['value' => 'list-card', 'framework_defaults' => false]], 'attributes' => ['table' => ['data-page' => 'list']], 'header_actions_in_filters' => true],
                    'dashboard' => ['framework_defaults' => false],
                ],
                'bundles' => ['NowoXBundle' => ['classes' => ['table' => 'x-table']]],
            ],
        ]);

        self::assertSame('list-table', $config['theme']['pages']['list']['classes']['table']);
        self::assertSame(['value' => 'list-card', 'framework_defaults' => false], $config['theme']['pages']['list']['classes']['card']);
        self::assertFalse($config['theme']['pages']['dashboard']['framework_defaults']);
        self::assertTrue($config['theme']['pages']['list']['header_actions_in_filters']);
        self::assertTrue(Theme::fromConfig('bootstrap5', $config['theme'])->listHeaderActionsInFilters());
        self::assertFalse(Theme::fromConfig('bootstrap5', [])->listHeaderActionsInFilters());
        self::assertArrayNotHasKey('partials', $config['theme']['pages']['list']);
        self::assertArrayNotHasKey('tokens', $config['theme']['pages']['list']);

        $theme = Theme::fromConfig('bootstrap5', $config['theme']);
        self::assertSame('nowo-ui-table nowo-ui-table--striped table table-hover align-middle host-table', $theme->classFor('table'));
        self::assertSame('nowo-ui-table nowo-ui-table--striped table table-hover align-middle list-table', $theme->classFor('table', null, null, 'list'));
        self::assertSame('nowo-ui-table nowo-ui-table--striped table table-hover align-middle x-table', $theme->classFor('table', 'NowoXBundle', null, 'list'));
        self::assertSame('nowo-ui-card list-card', $theme->classFor('card', null, null, 'list'));
        self::assertSame('nowo-ui-card host-card', $theme->classFor('card', null, null, 'dashboard'));
        self::assertSame(' data-host="1" data-page="list"', $theme->renderAttributes('table', null, [], 'list'));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'unknown page type' => [['theme' => ['pages' => ['index' => []]]], 'Invalid nowo_ui_kit.theme.pages key: unknown page type "index"'];
        yield 'unknown page slot' => [['theme' => ['pages' => ['list' => ['classes' => ['nope' => 'x']]]]], 'Unknown nowo_ui_kit.theme.pages.<page>.classes slot "nope"'];
        yield 'header actions option on a non-list page' => [['theme' => ['pages' => ['show' => ['header_actions_in_filters' => true]]]], 'Invalid nowo_ui_kit.theme.pages.show.header_actions_in_filters: only the list page type takes it.'];
        yield 'page partials not supported' => [['theme' => ['pages' => ['list' => ['partials' => ['card' => 'x']]]]], 'Unrecognized option "partials"'];
        yield 'page on* attribute' => [['theme' => ['pages' => ['form' => ['attributes' => ['form' => ['onsubmit' => 'x']]]]]], 'Invalid nowo_ui_kit.theme.pages.<page>.attributes.form'];
        yield 'unknown class entry key' => [['theme' => ['classes' => ['btn' => ['value' => 'x', 'framework' => false]]]], 'Invalid nowo_ui_kit.theme.classes.btn: unknown key(s) "framework"'];
        yield 'non-boolean framework_defaults entry' => [['theme' => ['classes' => ['btn' => ['value' => 'x', 'framework_defaults' => 'no']]]], '"framework_defaults" must be a boolean'];
        yield 'non-string class list' => [['theme' => ['classes' => ['btn' => ['a', ['b']]]]], 'class lists are strings or lists of strings'];
        yield 'non-string classes' => [['theme' => ['classes' => ['btn' => 3]]], 'class lists are plain strings'];
        yield 'unsafe bundle class entry' => [['theme' => ['bundles' => ['NowoXBundle' => ['classes' => ['btn' => ['value' => '<b>']]]]]], 'Invalid nowo_ui_kit.theme.bundles.<bundle>.classes.btn'];
        yield 'non-boolean theme framework_defaults' => [['theme' => ['framework_defaults' => 'no']], 'framework_defaults'];
        yield 'unknown class slot' => [['theme' => ['classes' => ['btn_moss' => 'x']]], 'Unknown nowo_ui_kit.theme.classes slot "btn_moss"'];
        yield 'unsafe classes' => [['theme' => ['classes' => ['btn' => 'x" onclick="y']]], 'class lists are plain strings'];
        yield 'unknown attribute slot' => [['theme' => ['attributes' => ['nope' => ['data-x' => '1']]]], 'Unknown nowo_ui_kit.theme.attributes slot "nope"'];
        yield 'on* attribute' => [['theme' => ['attributes' => ['btn' => ['onclick' => 'x()']]]], 'inline event handler attribute "onclick"'];
        yield 'invalid attribute name' => [['theme' => ['attributes' => ['btn' => ['a b' => 'x']]]], 'is not a valid attribute name'];
        yield 'non-scalar attribute' => [['theme' => ['attributes' => ['btn' => ['data-x' => ['a']]]]], 'values are strings, numbers, booleans or null'];
        yield 'unknown partial' => [['theme' => ['partials' => ['layout' => 'x.html.twig']]], 'Unknown nowo_ui_kit.theme.partials key "layout"'];
        yield 'bad token name' => [['theme' => ['tokens' => ['Color Primary' => 'red']]], 'Invalid nowo_ui_kit.theme.tokens: token name'];
        yield 'unsafe token value' => [['theme' => ['tokens_dark' => ['primary' => 'red;}body{x']]], 'Invalid nowo_ui_kit.theme.tokens_dark'];
        yield 'bad bundle key' => [['theme' => ['bundles' => ['nowo-x' => []]]], 'Invalid nowo_ui_kit.theme.bundles key "nowo-x"'];
        yield 'unknown bundle slot' => [['theme' => ['bundles' => ['NowoXBundle' => ['classes' => ['nope' => 'x']]]]], 'Unknown nowo_ui_kit.theme.bundles.<bundle>.classes slot "nope"'];
        yield 'unknown bundle partial' => [['theme' => ['bundles' => ['NowoXBundle' => ['partials' => ['nope' => 'x']]]]], 'Unknown nowo_ui_kit.theme.bundles.<bundle>.partials key "nope"'];
        yield 'unknown bundle key' => [['theme' => ['bundles' => ['NowoXBundle' => ['colors' => []]]]], 'Unrecognized option "colors"'];
        yield 'empty layout' => [['layouts' => ['admin' => '']], 'cannot contain an empty value'];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidConfigs')]
    public function testRejectsInvalidConfig(array $input, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        $this->process($input);
    }

    public function testExtensionRegistersThemeAndTwigExtension(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[
            'css_framework' => 'tailwind',
            'layouts' => ['admin' => 'layouts/admin.html.twig'],
            'theme' => ['classes' => ['btn' => 'x'], 'tokens' => ['primary' => 'red']],
        ]], $container);

        self::assertSame(['admin' => 'layouts/admin.html.twig', 'guest' => '@NowoUiKit/layout/guest.html.twig'], $container->getParameter('nowo_ui_kit.layouts'));
        self::assertSame(['btn' => 'x'], $container->getParameter('nowo_ui_kit.theme')['classes']);

        $theme = $container->getDefinition('nowo_ui_kit.theme');
        self::assertSame([Theme::class, 'fromConfig'], $theme->getFactory());
        self::assertSame('tailwind', $theme->getArgument(0));
        self::assertSame('nowo_ui_kit.theme', (string) $container->getAlias(Theme::class));

        $twig = $container->getDefinition('nowo_ui_kit.theme_twig_extension');
        self::assertSame(ThemeExtension::class, $twig->getClass());
        self::assertTrue($twig->hasTag('twig.extension'));
        self::assertSame(['breadcrumb_kit' => true, 'collection' => null], $twig->getArgument(2));

        $container->register('translator', \stdClass::class);
        $container->getDefinition('nowo_ui_kit.theme')->setPublic(true);
        $container->compile();
        $built = $container->get('nowo_ui_kit.theme');
        self::assertInstanceOf(Theme::class, $built);
        self::assertSame('tailwind', $built->framework());
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
