<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;

/**
 * Theming contract (1.13): kit output carries no hard-coded palette; every token is documented.
 */
final class ThemingTokensTest extends TestCase
{
    /**
     * Tailwind palette / radius / divide utilities that must never appear in kit output.
     */
    private const TAILWIND_PALETTE_PATTERN = '/(?<![\w-])(?:[a-z]+:)*(?:bg|text|border(?:-[trblxy])?|divide|ring|outline|from|via|to|fill|stroke|accent|placeholder|shadow)-(?:slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|white|black)(?:-\d{2,3})?(?![\w-])|(?<![\w-])(?:rounded(?:-[a-z0-9]+)?|divide-[xy](?:-\d)?)(?![\w-])/';

    private const MACRO_CALLS = [
        "ui.btn('primary')",
        "ui.btn('secondary', 'sm')",
        "ui.btn('danger')",
        "ui.btn('link')",
        "ui.btn('outline')",
        "ui.action('delete')",
        "ui.action('create')",
        'ui.toolbar()',
        'ui.page_header()',
        'ui.search_form()',
        'ui.input()',
        'ui.search_input()',
        'ui.list()',
        'ui.list_item()',
        'ui.table_wrap()',
        'ui.table()',
        'ui.row_actions()',
        'ui.muted()',
        'ui.empty()',
        'ui.link()',
        'ui.check()',
        "ui.flash('success')",
        "ui.flash('warning')",
        "ui.flash('info')",
        "ui.flash('error')",
        'ui.badge()',
        "ui.badge('success')",
        "ui.badge('warning')",
        "ui.badge('danger')",
        "ui.badge('info')",
        "ui.badge('neutral')",
        'ui.pagination_wrap()',
        'ui.tab_list()',
        'ui.tab_item(true)',
        'ui.tab_item(false)',
        'ui.header()',
        'ui.burger()',
        'ui.card()',
        'ui.card_header()',
        'ui.card_body()',
        'ui.card_footer()',
        'ui.filters()',
        'ui.progress()',
        'ui.progress_bar(40)',
        'ui.spinner()',
        "ui.spinner('sm')",
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function kitPaintedFrameworks(): iterable
    {
        yield 'tailwind' => ['tailwind'];
        yield 'custom' => ['custom'];
        yield 'none' => ['none'];
        yield 'foundation' => ['foundation'];
    }

    #[DataProvider('kitPaintedFrameworks')]
    public function testMacrosEmitNoHardCodedPalette(string $framework): void
    {
        foreach (self::MACRO_CALLS as $call) {
            $html = $this->renderMacro('{{ '.$call.' }}', $framework);
            self::assertStringStartsWith('nowo-ui-', $html, $call);
            self::assertSame(0, preg_match(self::TAILWIND_PALETTE_PATTERN, $html, $m), \sprintf('%s (%s) emits palette class "%s": %s', $call, $framework, $m[0] ?? '', $html));
        }
    }

    public function testBootstrapMacrosEmitNoBootstrapColourUtilitiesOutsideBadges(): void
    {
        foreach (self::MACRO_CALLS as $call) {
            if (str_starts_with($call, 'ui.badge') || str_starts_with($call, 'ui.muted') || str_starts_with($call, 'ui.empty')) {
                continue; // Bootstrap badge / muted helpers stay (themed through .nowo-ui-bs-tokens).
            }
            $html = $this->renderMacro('{{ '.$call.' }}', 'bootstrap5');
            self::assertSame(0, preg_match('/(?<![\w-])(?:bg|text)-(?:primary|secondary|success|danger|warning|info|light|dark|white|black|body)(?![\w-])/', $html), $call.': '.$html);
        }
    }

    public function testTailwindMacrosKeepLayoutUtilitiesAndSemanticClasses(): void
    {
        self::assertSame('nowo-ui-btn nowo-ui-btn--primary inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm', $this->renderMacro("{{ ui.btn('primary') }}", 'tailwind'));
        self::assertSame('nowo-ui-btn nowo-ui-btn--secondary nowo-ui-btn--sm inline-flex items-center gap-1 font-medium px-2 py-1 text-sm border', $this->renderMacro("{{ ui.btn('secondary', 'sm') }}", 'tailwind'));
        self::assertSame('nowo-ui-btn nowo-ui-btn--link inline-flex items-center gap-1 font-medium px-3 py-1.5 text-sm underline-offset-2 px-0', $this->renderMacro("{{ ui.btn('link') }}", 'tailwind'));
        self::assertSame('nowo-ui-tabs__item nowo-ui-tabs__item--active px-3 py-2 text-sm no-underline border-b-2', $this->renderMacro('{{ ui.tab_item(true) }}', 'tailwind'));
        self::assertSame('nowo-ui-muted', $this->renderMacro('{{ ui.muted() }}', 'tailwind'));
        self::assertSame('nowo-ui-flash nowo-ui-flash--warning px-4 py-3 text-sm mb-2 border', $this->renderMacro("{{ ui.flash('warning') }}", 'tailwind'));
    }

    public function testNewMacrosAndOutlineVariant(): void
    {
        self::assertSame('nowo-ui-link', $this->renderMacro('{{ ui.link() }}', 'bootstrap5'));
        self::assertSame('nowo-ui-check', $this->renderMacro('{{ ui.check() }}', 'tailwind'));
        self::assertStringContainsString('btn-outline-primary', $this->renderMacro("{{ ui.btn('outline') }}", 'bootstrap5'));
        self::assertStringContainsString('btn-outline-primary', $this->renderMacro("{{ ui.btn('outline') }}", 'bootstrap4'));
        self::assertStringContainsString('btn-outline-secondary', $this->renderMacro("{{ ui.btn('secondary') }}", 'bootstrap5'));
        self::assertStringContainsString('nowo-ui-btn--outline', $this->renderMacro("{{ ui.btn('outline') }}", 'custom'));
    }

    public function testPartialsAndComponentsCarryNoPaletteOrInlineColour(): void
    {
        $views = \dirname(__DIR__, 2).'/src/Resources/views';
        $files = array_merge(glob($views.'/partials/*.twig') ?: [], glob($views.'/components/*.twig') ?: [], [$views.'/macros/ui.html.twig']);
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $code = (string) preg_replace('/\{#.*?#\}/s', '', $source);
            self::assertSame(0, preg_match(self::TAILWIND_PALETTE_PATTERN, $code, $m), basename($file).': '.($m[0] ?? ''));
            $markup = (string) preg_replace('/\{\{.*?\}\}|\{%.*?%\}|href="[^"]*"/s', '', $code);
            self::assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $markup, $m), basename($file).': '.($m[0] ?? ''));
        }
    }

    public function testCssHasNoHexOutsideTokenBlocks(): void
    {
        $css = $this->css();
        $withoutTokenBlocks = (string) preg_replace('/^(?::root|\[data-theme="dark"\])[^{]*\{[^}]*\}/m', '', $css);
        self::assertSame(0, preg_match('/#[0-9a-fA-F]{3,8}\b/', $withoutTokenBlocks, $m), 'Hard-coded hex outside token blocks: '.($m[0] ?? ''));
        self::assertSame(0, preg_match('/var\(--nowo-ui-[a-z-]+, #/', $css), 'Base tokens are always declared; no hex fallbacks.');
    }

    public function testBaseTokensHaveLightAndDarkDefaults(): void
    {
        $css = $this->css();
        self::assertSame(1, preg_match('/^:root:where\(:not\(\[data-nowo-ui-host-tokens\]\)\),\n:where\(:root\[data-nowo-ui-host-tokens\]\) \{([^}]*)\}/m', $css, $light));
        self::assertSame(1, preg_match('/^\[data-theme="dark"\]:where\(:not\(\[data-nowo-ui-host-tokens\], \[data-nowo-ui-host-tokens\] \*\)\),\n:where\(\[data-nowo-ui-host-tokens\]\[data-theme="dark"\], \[data-nowo-ui-host-tokens\] \[data-theme="dark"\]\) \{([^}]*)\}/m', $css, $dark));
        foreach (['primary', 'primary-contrast', 'danger', 'danger-text', 'danger-bg', 'danger-border', 'danger-text-strong', 'success-bg', 'success-border', 'success-text', 'warning-bg', 'warning-border', 'warning-text', 'info-bg', 'info-border', 'info-text', 'surface', 'surface-muted', 'border', 'border-strong', 'text', 'muted'] as $token) {
            self::assertStringContainsString('--nowo-ui-'.$token.':', $light[1], $token);
            self::assertStringContainsString('--nowo-ui-'.$token.':', $dark[1], $token);
        }
        // Unchanged 1.12 defaults (default look stays identical).
        self::assertStringContainsString('--nowo-ui-primary: #2563eb;', $light[1]);
        self::assertStringContainsString('--nowo-ui-border-strong: #cbd5e1;', $light[1]);
        self::assertStringContainsString('--nowo-ui-radius: 0.375rem;', $light[1]);
        self::assertStringContainsString('--nowo-ui-primary: #60a5fa;', $dark[1]);
    }

    public function testComponentTokensResolveThroughFallbacksToBaseTokens(): void
    {
        $css = $this->css();
        foreach ([
            'background: var(--nowo-ui-btn-primary-bg, var(--nowo-ui-primary));',
            'border: 1px solid var(--nowo-ui-input-border, var(--nowo-ui-border-strong));',
            'background-color: var(--nowo-ui-input-bg, var(--nowo-ui-surface));',
            'color: var(--nowo-ui-input-placeholder, var(--nowo-ui-muted));',
            'color: var(--nowo-ui-table-head-text, var(--nowo-ui-muted));',
            'background: var(--nowo-ui-pagination-active-bg, var(--nowo-ui-primary));',
            'border-bottom-color: var(--nowo-ui-tab-active-border, var(--nowo-ui-primary));',
            'color: var(--nowo-ui-link, var(--nowo-ui-primary));',
            'font-family: var(--nowo-ui-font-display, inherit);',
            'accent-color: var(--nowo-ui-accent, var(--nowo-ui-primary));',
            'var(--nowo-ui-focus-ring-color, color-mix(in srgb, var(--nowo-ui-primary) 35%, transparent))',
        ] as $needle) {
            self::assertStringContainsString($needle, $css);
        }
        // Component tokens are optional: never declared, so scoped base-token remaps keep cascading.
        self::assertSame(0, preg_match('/^\s*--nowo-ui-(?:btn-|input-|tab-|tabs-|pagination-|list-|link|focus-|font-|accent|shadow-|overlay|card-|modal-|badge-|nav-|progress-|primary-hover|heading-|table-(?:border|head-bg|head-text|stripe-bg|hover-bg))[a-z-]*\s*:/m', $css, $m), 'Declared component token: '.($m[0] ?? ''));
    }

    public function testBootstrapBridgeIsOptIn(): void
    {
        $css = $this->css();
        self::assertSame(1, preg_match('/\/\* --- Bootstrap token bridge.*?(?=\/\* --- Dark theme token defaults)/s', $css, $bridge));
        $rules = preg_split('/\}\s*/', (string) preg_replace('/\/\*.*?\*\//s', '', $bridge[0])) ?: [];
        foreach ($rules as $rule) {
            if ('' === trim($rule)) {
                continue;
            }
            $selector = trim(explode('{', $rule, 2)[0]);
            foreach (explode(',', $selector) as $part) {
                self::assertStringStartsWith('.nowo-ui-bs-tokens', trim($part), $selector);
            }
        }
        self::assertStringContainsString('--bs-btn-bg: var(--nowo-ui-btn-primary-bg, var(--nowo-ui-primary));', $bridge[0]);
    }

    public function testEveryCssTokenIsDocumentedInThemingGuide(): void
    {
        $doc = (string) file_get_contents(\dirname(__DIR__, 2).'/docs/THEMING.md');
        $css = (string) preg_replace('/\/\*.*?\*\//s', '', $this->css());
        preg_match_all('/--nowo-ui-[a-z0-9-]*[a-z0-9]/', $css, $m);
        $tokens = array_values(array_unique($m[0]));
        self::assertGreaterThan(80, \count($tokens));
        foreach ($tokens as $token) {
            self::assertStringContainsString('`'.$token.'`', $doc, $token.' is used in nowo-ui.css but missing from docs/THEMING.md');
        }
    }

    private function css(): string
    {
        return (string) file_get_contents(\dirname(__DIR__, 2).'/src/Resources/public/css/nowo-ui.css');
    }

    private function renderMacro(string $expression, string $framework): string
    {
        $fs = new FilesystemLoader();
        $fs->addPath(\dirname(__DIR__, 2).'/src/Resources/views', 'NowoUiKitBundle');
        $loader = new ArrayLoader(['t.twig' => "{% import '@NowoUiKitBundle/macros/ui.html.twig' as ui %}{$expression}"]);
        $twig = new Environment(new ChainLoader([$loader, $fs]));
        $twig->addGlobal('nowo_ui_kit_css_framework', $framework);

        return trim((string) preg_replace('/\s+/', ' ', $twig->render('t.twig')));
    }
}
