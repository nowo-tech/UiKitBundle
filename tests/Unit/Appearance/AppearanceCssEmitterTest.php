<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Appearance;

use Nowo\UiKitBundle\Appearance\Appearance;
use Nowo\UiKitBundle\Appearance\AppearanceCssEmitter;
use Nowo\UiKitBundle\Appearance\AppearancePalette;
use Nowo\UiKitBundle\Appearance\BorderStrength;
use Nowo\UiKitBundle\Appearance\BuiltInAppearancePresets;
use Nowo\UiKitBundle\Appearance\CornerStyle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppearanceCssEmitterTest extends TestCase
{
    /**
     * The alias map documented in THEMING.md to keep a host's legacy `--brand-*` / `--color-*` names.
     */
    public const LEGACY_ALIASES = [
        '--nowo-ui-card-radius' => ['--beacon-radius-card'],
        '--nowo-ui-radius' => ['--beacon-radius-control'],
        '--nowo-ui-border-width' => ['--beacon-border-width'],
        '--nowo-ui-primary' => ['--beacon-moss', '--color-moss'],
        '--nowo-ui-primary-deep' => ['--beacon-moss-deep', '--color-moss-deep'],
        '--nowo-ui-danger' => ['--beacon-alert', '--color-alert'],
        '--nowo-ui-warning' => ['--beacon-warn', '--color-warn'],
        '--nowo-ui-paper' => ['--beacon-paper', '--color-paper'],
        '--nowo-ui-text' => ['--beacon-ink', '--color-ink'],
        '--nowo-ui-surface' => ['--beacon-surface', '--color-surface'],
        '--nowo-ui-border' => ['--beacon-sand', '--color-sand'],
        '--nowo-ui-accent-soft' => ['--beacon-mist', '--color-mist'],
        '--nowo-ui-surface-muted' => ['--beacon-surface-muted', '--color-surface-muted'],
        '--nowo-ui-paper-accent' => ['--beacon-shell-a'],
        '--nowo-ui-paper-ink' => ['--beacon-shell-b'],
        '--nowo-ui-brand-mark' => ['--beacon-mark'],
        '--nowo-ui-shadow-rgb' => ['--beacon-shadow'],
    ];

    public function testEmitsKitTokensInThreeBlocks(): void
    {
        $css = (new AppearanceCssEmitter())->emit($this->appearance(CornerStyle::Soft, BorderStrength::Medium));

        self::assertStringStartsWith(":root {\n  --nowo-ui-radius: 0.375rem;\n  --nowo-ui-card-radius: 0.75rem;\n  --nowo-ui-border-width: 1px;\n}\n:root, [data-theme=\"light\"] {\n", $css);
        self::assertStringContainsString("\n[data-theme=\"dark\"] {\n", $css);
        self::assertStringEndsWith('}', $css);
        $blocks = $this->parse($css);
        self::assertSame(AppearanceCssEmitter::ROOT_TOKENS, array_keys($blocks[':root']));
        self::assertSame(AppearanceCssEmitter::MODE_TOKENS, array_keys($blocks[':root, [data-theme="light"]']));
        self::assertSame(AppearanceCssEmitter::MODE_TOKENS, array_keys($blocks['[data-theme="dark"]']));

        $light = $blocks[':root, [data-theme="light"]'];
        self::assertSame('#475569', $light['--nowo-ui-primary']);
        self::assertSame('#ffffff', $light['--nowo-ui-primary-contrast']);
        self::assertSame('color-mix(in srgb, #0f172a 14%, #f1f5f9)', $light['--nowo-ui-border']);
        self::assertSame('15 23 42', $light['--nowo-ui-shadow-rgb']);
        $dark = $blocks['[data-theme="dark"]'];
        self::assertSame('color-mix(in srgb, #e0e7ff 16%, #070b16)', $dark['--nowo-ui-border']);
        self::assertSame('#0f1524', $dark['--nowo-ui-primary-contrast']);
        self::assertSame('0 0 0', $dark['--nowo-ui-shadow-rgb']);
    }

    public function testAliasesSelectorsAndAliasOnlyMode(): void
    {
        $appearance = $this->appearance(CornerStyle::Sharp, BorderStrength::Strong);
        $emitter = new AppearanceCssEmitter(['--nowo-ui-primary' => ['--brand-primary'], '--nowo-ui-radius' => ['--brand-radius']], true, ['light' => 'html:root', 'dark' => 'html:root[data-theme="dark"]']);
        $css = $emitter->emit($appearance);
        self::assertStringContainsString("  --nowo-ui-primary: #475569;\n  --brand-primary: #475569;\n", $css);
        self::assertStringContainsString("  --nowo-ui-radius: 0.0625rem;\n  --brand-radius: 0.0625rem;\n", $css);
        self::assertStringContainsString("html:root {\n", $css);
        self::assertStringContainsString("html:root[data-theme=\"dark\"] {\n", $css);
        self::assertStringContainsString('--nowo-ui-border-width: 1.5px;', $css);

        $only = (new AppearanceCssEmitter(['--nowo-ui-primary' => ['--brand-primary']], false))->emit($appearance);
        self::assertStringNotContainsString('--nowo-ui-', $only);
        self::assertSame(2, substr_count($only, '--brand-primary'));
    }

    public function testInvalidColoursFallBackAndNeverBreakOutOfTheStyle(): void
    {
        $slate = BuiltInAppearancePresets::get('slate');
        $bad = new Appearance(
            'x',
            '',
            AppearancePalette::fromArray(['accent' => 'red;}</style><script>', 'ink' => 'rgb(1 2 3)', 'surface' => 'url(x)'], $slate->light),
            AppearancePalette::fromArray(['paper' => 'expression(alert(1))', 'accent' => 'oklch(70% 0.1 200)'], $slate->dark),
        );
        $emitter = new AppearanceCssEmitter([], true, [], '1 2 3');
        $tokens = $emitter->tokens($bad);
        self::assertSame($slate->light->accent, $tokens['light']['--nowo-ui-primary']);
        self::assertSame('rgb(1 2 3)', $tokens['light']['--nowo-ui-text']);
        self::assertSame($slate->light->surface, $tokens['light']['--nowo-ui-surface']);
        self::assertSame('1 2 3', $tokens['light']['--nowo-ui-shadow-rgb']);
        self::assertSame(BuiltInAppearancePresets::get('midnight')->dark->paper, $tokens['dark']['--nowo-ui-paper']);
        self::assertSame('oklch(70% 0.1 200)', $tokens['dark']['--nowo-ui-primary']);
        self::assertArrayNotHasKey('--nowo-ui-primary-contrast', $tokens['dark']);
        self::assertStringNotContainsString('<', $emitter->emit($bad));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function colors(): iterable
    {
        yield 'hex6' => ['#1f6f54', true];
        yield 'hex3' => ['#abc', true];
        yield 'hex8' => ['#1f6f54cc', true];
        yield 'named' => ['rebeccapurple', true];
        yield 'rgb' => ['rgb(10, 20, 30)', true];
        yield 'color-mix' => ['color-mix(in srgb, #fff 10%, #000)', true];
        yield 'hex5' => ['#12345', false];
        yield 'semicolon' => ['red; x: 1', false];
        yield 'url' => ['url(a)', false];
        yield 'brace' => ['rgb(1,2,3)}', false];
        yield 'empty' => ['', false];
    }

    #[DataProvider('colors')]
    public function testColourValidation(string $value, bool $valid): void
    {
        self::assertSame($valid, AppearanceCssEmitter::isColor($value));
    }

    public function testChannelsAndContrastHelpers(): void
    {
        self::assertSame('15 28 24', AppearanceCssEmitter::rgbChannels('#0f1c18'));
        self::assertSame('170 187 204', AppearanceCssEmitter::rgbChannels('#abc'));
        self::assertNull(AppearanceCssEmitter::rgbChannels('0f1c18'));
        self::assertNull(AppearanceCssEmitter::rgbChannels('#0f1c18cc'));
        self::assertNull(AppearanceCssEmitter::rgbChannels('red'));
        self::assertSame('#ffffff', AppearanceCssEmitter::contrastingOf('#1f6f54', '#ffffff', '#0f1c18'));
        self::assertSame('#151c19', AppearanceCssEmitter::contrastingOf('#4aad7f', '#151c19', '#e6eee9'));
        self::assertNull(AppearanceCssEmitter::contrastingOf('red', '#fff', '#000'));
    }

    /**
     * The alias map + `kit_tokens: false` reproduces a host emitter that writes legacy names (literal values
     * instead of `var()` indirections: same computed values, and no cycle when the host maps
     * `--nowo-ui-*` back onto its names).
     *
     * @return iterable<string, array{CornerStyle, BorderStrength}>
     */
    public static function styles(): iterable
    {
        foreach (CornerStyle::cases() as $corner) {
            foreach (BorderStrength::cases() as $border) {
                yield $corner->value.'/'.$border->value => [$corner, $border];
            }
        }
    }

    #[DataProvider('styles')]
    public function testAliasesReproduceTheLegacyHostOutput(CornerStyle $corner, BorderStrength $border): void
    {
        $appearance = new Appearance(
            'Brand',
            '',
            new AppearancePalette('#1f6f54', '#134736', '#b42318', '#b54708', '#f3f6f4', '#0f1c18', '#ffffff'),
            new AppearancePalette('#4aad7f', '#6bc49a', '#f97066', '#fdb022', '#0c1210', '#e6eee9', '#151c19'),
            'brand',
            'brand',
            $corner->value,
            $border->value,
        );
        $kit = $this->parse((new AppearanceCssEmitter(self::LEGACY_ALIASES, false, [], '15 28 24'))->emit($appearance));
        $legacy = $this->parse($this->legacyHostCss($appearance));

        self::assertSame(array_keys($legacy), array_keys($kit));
        foreach ($legacy as $selector => $declarations) {
            $resolved = array_map(static fn (string $v): string => preg_replace_callback('/^var\((--[a-z-]+)\)$/', static fn (array $m): string => $declarations[$m[1]], $v) ?? $v, $declarations);
            ksort($resolved);
            $actual = $kit[$selector];
            ksort($actual);
            self::assertSame($resolved, $actual, $selector);
        }
    }

    /**
     * Reference implementation of the host emitter the aliases replace (Symfony Beacon 1.59
     * `SiteAppearanceProvider::getCssOverrides()`), kept verbatim in shape.
     */
    private function legacyHostCss(Appearance $a): string
    {
        $radii = $a->corner()->radii();
        $borders = $a->border()->tokens();
        $block = static function (AppearancePalette $p, int $sandMix, bool $dark): array {
            $lines = [
                \sprintf('  --beacon-moss: %s;', $p->accent),
                \sprintf('  --beacon-moss-deep: %s;', $p->accentDeep),
                \sprintf('  --beacon-alert: %s;', $p->danger),
                \sprintf('  --beacon-warn: %s;', $p->warn),
                \sprintf('  --beacon-paper: %s;', $p->paper),
                \sprintf('  --beacon-ink: %s;', $p->ink),
                \sprintf('  --beacon-surface: %s;', $p->surface),
                \sprintf('  --beacon-sand: color-mix(in srgb, %s %d%%, %s);', $p->ink, $sandMix, $p->paper),
                \sprintf('  --beacon-mist: color-mix(in srgb, %s 10%%, %s);', $p->accent, $p->paper),
                \sprintf('  --beacon-surface-muted: color-mix(in srgb, %s 5%%, %s);', $p->ink, $p->surface),
                \sprintf('  --beacon-shell-a: color-mix(in srgb, %s 8%%, %s);', $p->accent, $p->paper),
                \sprintf('  --beacon-shell-b: color-mix(in srgb, %s 6%%, %s);', $p->ink, $p->paper),
                \sprintf('  --beacon-mark: %s;', $p->accent),
                '  --color-sand: var(--beacon-sand);',
                '  --color-mist: var(--beacon-mist);',
                '  --color-ink: var(--beacon-ink);',
                '  --color-paper: var(--beacon-paper);',
                '  --color-moss: var(--beacon-moss);',
                '  --color-moss-deep: var(--beacon-moss-deep);',
                '  --color-alert: var(--beacon-alert);',
                '  --color-warn: var(--beacon-warn);',
                '  --color-surface: var(--beacon-surface);',
                '  --color-surface-muted: var(--beacon-surface-muted);',
            ];
            $lines[] = $dark ? '  --beacon-shadow: 0 0 0;' : \sprintf('  --beacon-shadow: %s;', AppearanceCssEmitter::rgbChannels($p->ink) ?? '15 28 24');

            return $lines;
        };

        return implode("\n", [
            ':root {',
            \sprintf('  --beacon-radius-card: %s;', $radii['card']),
            \sprintf('  --beacon-radius-control: %s;', $radii['control']),
            \sprintf('  --beacon-border-width: %s;', $borders['width']),
            '}',
            ':root, [data-theme="light"] {',
            ...$block($a->light, $borders['mixLight'], false),
            '}',
            '[data-theme="dark"] {',
            ...$block($a->dark, $borders['mixDark'], true),
            '}',
        ]);
    }

    /**
     * @return array<string, array<string, string>> selector => [property => value]
     */
    private function parse(string $css): array
    {
        preg_match_all('/^([^\n{]+) \{\n(.*?)\n\}/ms', $css, $m, \PREG_SET_ORDER);
        $blocks = [];
        foreach ($m as [, $selector, $body]) {
            foreach (explode("\n", $body) as $line) {
                if (1 === preg_match('/^\s+(--[A-Za-z0-9_-]+): (.*);$/', $line, $d)) {
                    $blocks[$selector][$d[1]] = $d[2];
                }
            }
        }

        return $blocks;
    }

    private function appearance(CornerStyle $corner, BorderStrength $border): Appearance
    {
        return new Appearance('Acme', '', BuiltInAppearancePresets::get('slate')->light, BuiltInAppearancePresets::get('midnight')->dark, 'slate', 'midnight', $corner->value, $border->value);
    }
}
