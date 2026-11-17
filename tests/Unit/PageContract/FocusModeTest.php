<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Shell\FocusMode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Twig\Environment;

/**
 * 2.54 shell focus mode: `nowo_ui_kit.theme.focus_mode`, the Twig global, the shell root attributes and focus bar of
 * `app_shell_open`, the `focus_mode_toggle` / `focus_mode_bar` partials and the scripts partial. Off by default: the
 * shell, the scripts and both partials render exactly the 2.53 markup.
 */
final class FocusModeTest extends TestCase
{
    private const HALVES = <<<'TWIG'
{{ include(nowo_ui_partial('app_shell_open'), opts) }}<main id="main-content" tabindex="-1">BODY</main>{{ include(nowo_ui_partial('app_shell_close'), {}) }}
TWIG;

    private const ENABLED = ['enabled' => true, 'regions' => FocusMode::DEFAULT_REGIONS, 'keep' => [], 'shortcut' => 'F', 'fullscreen' => true];

    public function testConfigDefaultsAndOverrides(): void
    {
        self::assertSame(['enabled' => false, 'regions' => ['sidebar', 'header', 'footer', 'side_panels', 'edge_tabs', 'dock'], 'keep' => [], 'shortcut' => 'F', 'fullscreen' => true], $this->process([])['theme']['focus_mode']);

        $config = $this->process(
            ['theme' => ['focus_mode' => ['enabled' => true, 'regions' => ['sidebar', 'header'], 'keep' => ['breadcrumb_min', 'search', 'timer_chip'], 'shortcut' => 'Alt+Shift+F', 'fullscreen' => false]]],
            ['theme' => ['focus_mode' => ['regions' => ['sidebar', 'dock']]]],
        )['theme']['focus_mode'];
        self::assertSame(['enabled' => true, 'regions' => ['sidebar', 'dock'], 'keep' => ['breadcrumb_min', 'search', 'timer_chip'], 'shortcut' => 'Alt+Shift+F', 'fullscreen' => false], $config, 'a later list replaces the earlier one');

        foreach ([null, false, ''] as $off) {
            self::assertNull($this->process(['theme' => ['focus_mode' => ['shortcut' => $off]]])['theme']['focus_mode']['shortcut'], var_export($off, true));
        }
    }

    public function testInvalidConfigIsRejected(): void
    {
        foreach ([
            [['regions' => ['Sidebar']], 'focus_mode.regions'],
            [['regions' => ['x" onclick="1']], 'focus_mode.regions'],
            [['keep' => ['search box']], 'focus_mode.keep'],
            [['keep' => [3]], 'focus_mode.keep'],
            [['shortcut' => 'Ctrl+FF'], 'focus_mode.shortcut'],
            [['shortcut' => 'Escape'], 'focus_mode.shortcut'],
        ] as [$focus, $message]) {
            try {
                $this->process(['theme' => ['focus_mode' => $focus]]);
                self::fail('rejected: '.json_encode($focus));
            } catch (InvalidConfigurationException $e) {
                self::assertStringContainsString('Invalid nowo_ui_kit.theme.'.$message, $e->getMessage());
            }
        }
    }

    public function testNameAndShortcutRules(): void
    {
        self::assertTrue(FocusMode::isValidName('timer_chip'));
        self::assertFalse(FocusMode::isValidName('_x'));
        self::assertFalse(FocusMode::isValidName(str_repeat('a', 33)));
        self::assertFalse(FocusMode::isValidName(null));
        self::assertTrue(FocusMode::isValidShortcut('f'));
        self::assertTrue(FocusMode::isValidShortcut('Ctrl+Alt+Shift+Meta+9'));
        self::assertFalse(FocusMode::isValidShortcut('Hyper+F'));
        self::assertFalse(FocusMode::isValidShortcut(['F']));
    }

    public function testTheTwigGlobalCarriesTheProcessedConfig(): void
    {
        $container = new ContainerBuilder();
        $container->registerExtension(new class extends Extension {
            public function getAlias(): string
            {
                return 'twig';
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }
        });
        $container->prependExtensionConfig(Configuration::ALIAS, ['theme' => ['focus_mode' => ['enabled' => true, 'keep' => ['search']]]]);
        (new NowoUiKitExtension())->prepend($container);
        $globals = $container->getExtensionConfig('twig')[0]['globals'] ?? [];

        self::assertEquals(['enabled' => true, 'regions' => FocusMode::DEFAULT_REGIONS, 'keep' => ['search'], 'shortcut' => 'F', 'fullscreen' => true], $globals['nowo_ui_kit_focus_mode'] ?? null);
    }

    public function testOffByDefaultTheShellAndTheScriptsAreUnchanged(): void
    {
        $opts = ['opts' => ['brand' => 'Acme', 'focus_area' => 'wiki']];
        $plain = $this->twig()->render('t', $opts);
        self::assertSame($plain, $this->twig(['enabled' => false] + self::ENABLED)->render('t', $opts));
        self::assertStringNotContainsString('data-nowo-ui-focus', $plain);

        $scripts = $this->twig()->render('s');
        self::assertSame($scripts, $this->twig(['enabled' => false] + self::ENABLED)->render('s'));
        self::assertStringNotContainsString('focus-mode', $scripts);

        self::assertSame('', trim($this->twig()->render('toggle', ['o' => ['area' => 'wiki']])));
        self::assertSame('', trim($this->twig()->render('bar', ['o' => []])));
    }

    public function testEnabledShellRootAttributesAndFocusBar(): void
    {
        $html = $this->twig(['keep' => ['search', 'timer_chip'], 'shortcut' => 'Alt+Shift+F', 'fullscreen' => false] + self::ENABLED)->render('t', ['opts' => ['brand' => 'Acme', 'focus_area' => 'wiki']]);

        self::assertStringContainsString('<div class="nowo-ui-app-shell nowo-ui-shell is-content-width" data-nowo-ui-shell data-nowo-ui-focus-regions="sidebar header footer side_panels edge_tabs dock" data-nowo-ui-focus-keep-list="search timer_chip" data-nowo-ui-focus-shortcut="Alt+Shift+F" data-nowo-ui-focus-fullscreen="false" data-nowo-ui-focus-area="wiki">', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-app-shell__main nowo-ui-main">\s*<div class="nowo-ui-focus-bar" data-nowo-ui-focus-bar>#', $html);
        self::assertStringNotContainsString('data-nowo-ui-focus-mode="fullscreen"', $html);
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-focus-bar__exit btn btn-sm btn-outline-secondary" data-nowo-ui-focus-mode="exit" title="Exit focus mode" aria-keyshortcuts="Escape">', $html);
        self::assertStringContainsString('<span class="nowo-ui-focus-bar__exit-label">Exit focus</span></button>', $html);
        self::assertStringContainsString('<p class="visually-hidden" role="status" aria-live="polite" data-nowo-ui-focus-status data-nowo-ui-focus-msg-on="Focus mode on. Press Escape to leave it." data-nowo-ui-focus-msg-off="Focus mode off." data-nowo-ui-focus-msg-fullscreen="Full screen on." data-nowo-ui-focus-msg-fullscreen-off="Full screen off. Focus mode stays on." data-nowo-ui-focus-msg-fullscreen-unavailable="Full screen is not available. Focus mode stays on."></p>', $html);
        self::assertStringNotContainsString('nowo-ui-focus-bar--crumbs', $html);
        self::assertSame(0, preg_match('/\s(on[a-z]+|style)=/i', $html));

        // Defaults: no keep list, F, fullscreen allowed (no attribute), no area; a bad area is dropped.
        $default = $this->twig(self::ENABLED)->render('t', ['opts' => ['brand' => 'Acme', 'focus_area' => 'bad key']]);
        self::assertStringContainsString('data-nowo-ui-shell data-nowo-ui-focus-regions="sidebar header footer side_panels edge_tabs dock" data-nowo-ui-focus-shortcut="F">', $default);
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--icon nowo-ui-focus-bar__fullscreen btn btn-sm btn-outline-secondary" data-nowo-ui-focus-mode="fullscreen" aria-pressed="false" title="Full screen">', $default);

        // No shortcut; a page can opt out with focus_mode: false.
        self::assertStringContainsString('data-nowo-ui-focus-shortcut="none"', $this->twig(['shortcut' => null] + self::ENABLED)->render('t', ['opts' => ['brand' => 'Acme']]));
        $out = $this->twig(self::ENABLED)->render('t', ['opts' => ['brand' => 'Acme', 'focus_mode' => false]]);
        self::assertSame($this->twig()->render('t', ['opts' => ['brand' => 'Acme']]), $out);
    }

    public function testBreadcrumbMinPutsTheCompactTrailInTheBar(): void
    {
        $html = $this->twig(['keep' => ['breadcrumb_min']] + self::ENABLED)->render('t', ['opts' => ['brand' => 'Acme', 'focus_crumbs' => [['label' => 'Wiki', 'href' => '/wiki'], ['label' => 'Space', 'href' => '/wiki/s'], ['label' => 'Parent', 'href' => '/wiki/s/p'], ['label' => 'Page']]]]);

        self::assertStringContainsString('<div class="nowo-ui-focus-bar nowo-ui-focus-bar--crumbs" data-nowo-ui-focus-bar>', $html);
        self::assertStringContainsString('<div class="nowo-ui-focus-bar__crumbs"><nav class="nowo-ui-header-crumbs"', $html);
        self::assertStringContainsString('id="nowo-ui-focus-crumbs-panel"', $html);
        self::assertStringContainsString('aria-current="page" title="Page">Page</span>', $html);
        self::assertStringContainsString('href="/wiki/s"', $html);
    }

    public function testTheTogglePartial(): void
    {
        $twig = $this->twig(self::ENABLED);
        $html = trim($twig->render('toggle', ['o' => ['area' => 'wiki', 'attr' => ['class' => 'x', 'data-testid' => 'focus', 'data-nowo-ui-focus-mode' => 'exit', 'aria-pressed' => 'evil']]]));

        self::assertStringStartsWith('<button type="button" class="nowo-ui-btn nowo-ui-btn--icon nowo-ui-focus-mode-toggle btn btn-sm btn-outline-secondary x" data-nowo-ui-focus-mode aria-pressed="false" data-nowo-ui-focus-area="wiki" aria-keyshortcuts="F" title="Focus mode (F)" data-testid="focus">', $html);
        self::assertStringContainsString('<span class="nowo-ui-focus-mode-toggle__icon" aria-hidden="true"><svg class="nowo-ui-icon"', $html);
        self::assertStringContainsString('<span class="visually-hidden">Focus mode</span>', $html);
        self::assertStringNotContainsString('evil', $html);

        $fullscreen = trim($twig->render('toggle', ['o' => ['level' => 'fullscreen', 'area' => ['x'], 'show_label' => true, 'label' => 'Present']]));
        self::assertStringStartsWith('<button type="button" class="nowo-ui-btn nowo-ui-btn--icon nowo-ui-focus-mode-toggle btn btn-sm btn-outline-secondary" data-nowo-ui-focus-mode="fullscreen" aria-pressed="false">', $fullscreen);
        self::assertStringContainsString('<span class="nowo-ui-focus-mode-toggle__label">Present</span>', $fullscreen);

        self::assertSame('', trim($this->twig(['fullscreen' => false] + self::ENABLED)->render('toggle', ['o' => ['level' => 'fullscreen']])));
        self::assertStringNotContainsString('aria-keyshortcuts', $this->twig(['shortcut' => null] + self::ENABLED)->render('toggle', ['o' => []]));
    }

    public function testTheBarCanDropItsFullscreenButtonAndTheScriptsLoadTheRuntime(): void
    {
        $twig = $this->twig(self::ENABLED);
        self::assertStringNotContainsString('fullscreen"', $twig->render('bar', ['o' => ['fullscreen' => false]]));

        $scripts = $twig->render('s');
        self::assertStringContainsString('<script src="/bundles/nowouikit/js/nowo-ui-focus-mode.js" defer></script>', $scripts);
        self::assertSame(1, substr_count($twig->render('s2'), 'nowo-ui-focus-mode.js'), 'listed by the host already: printed once');
    }

    /**
     * @param array<string, mixed>|null $focus nowo_ui_kit_focus_mode global (null = not defined)
     */
    private function twig(?array $focus = null): Environment
    {
        $twig = PageContractTwig::create([
            't' => self::HALVES,
            's' => "{{ include(nowo_ui_partial('scripts')) }}",
            's2' => "{{ include(nowo_ui_partial('scripts'), {script_names: ['modal', 'focus-mode']}) }}",
            'toggle' => "{{ include(nowo_ui_partial('focus_mode_toggle'), o) }}",
            'bar' => "{{ include(nowo_ui_partial('focus_mode_bar'), o) }}",
        ]);
        if (null !== $focus) {
            $twig->addGlobal('nowo_ui_kit_focus_mode', $focus);
        }

        return $twig;
    }

    /**
     * @param array<string, mixed> ...$configs
     *
     * @return array<string, mixed>
     */
    private function process(array ...$configs): array
    {
        /** @var array<string, mixed> $processed */
        $processed = (new Processor())->processConfiguration(new Configuration(), $configs);

        return $processed;
    }
}
