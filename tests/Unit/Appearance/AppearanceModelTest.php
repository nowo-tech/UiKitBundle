<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Appearance;

use Nowo\UiKitBundle\Appearance\Appearance;
use Nowo\UiKitBundle\Appearance\AppearancePalette;
use Nowo\UiKitBundle\Appearance\AppearancePreset;
use Nowo\UiKitBundle\Appearance\AppearancePresetProviderInterface;
use Nowo\UiKitBundle\Appearance\AppearancePresetRegistry;
use Nowo\UiKitBundle\Appearance\BorderStrength;
use Nowo\UiKitBundle\Appearance\BuiltInAppearancePresets;
use Nowo\UiKitBundle\Appearance\ConfigAppearanceProvider;
use Nowo\UiKitBundle\Appearance\CornerStyle;
use PHPUnit\Framework\TestCase;

final class AppearanceModelTest extends TestCase
{
    public function testPaletteArrayRoundTripAndFallbacks(): void
    {
        $slate = BuiltInAppearancePresets::get('slate')->light;
        $palette = AppearancePalette::fromArray(['accent' => ' #111111 ', 'accentDeep' => '#222222', 'ink' => '', 'paper' => 5]);
        self::assertSame('#111111', $palette->accent);
        self::assertSame('#222222', $palette->accentDeep);
        self::assertSame($slate->ink, $palette->ink);
        self::assertSame($slate->paper, $palette->paper);
        self::assertSame(AppearancePalette::KEYS, array_keys($palette->toArray()));
        self::assertEquals($palette, AppearancePalette::fromArray($palette->toArray()));

        self::assertTrue($slate->equals(AppearancePalette::fromArray(array_map(strtoupper(...), $slate->toArray()))));
        self::assertFalse($slate->equals($palette));
    }

    public function testEnumsResolveLooseValuesAndMaps(): void
    {
        self::assertSame(CornerStyle::Soft, CornerStyle::fromLoose(null));
        self::assertSame(CornerStyle::Soft, CornerStyle::fromLoose('bogus'));
        self::assertSame(['card' => '0.125rem', 'control' => '0.0625rem'], CornerStyle::Sharp->radii());
        self::assertSame(['card' => '0.75rem', 'control' => '0.375rem'], CornerStyle::Soft->radii());
        self::assertSame(['card' => '1.25rem', 'control' => '0.5rem'], CornerStyle::fromLoose('rounded')->radii());
        self::assertSame(['sharp', 'soft', 'rounded'], CornerStyle::values());

        self::assertSame(BorderStrength::Medium, BorderStrength::fromLoose(''));
        self::assertSame(['mixLight' => 7, 'mixDark' => 9, 'width' => '1px'], BorderStrength::Subtle->tokens());
        self::assertSame(['mixLight' => 14, 'mixDark' => 16, 'width' => '1px'], BorderStrength::Medium->tokens());
        self::assertSame(['mixLight' => 26, 'mixDark' => 30, 'width' => '1.5px'], BorderStrength::fromLoose('strong')->tokens());
        self::assertSame(['subtle', 'medium', 'strong'], BorderStrength::values());
    }

    public function testBuiltInPresetsMatchTheOriginalPalettes(): void
    {
        self::assertSame(['ocean', 'slate', 'sandstone', 'midnight', 'obsidian', 'aurora', 'ember'], array_map(static fn (AppearancePreset $p): string => $p->id, BuiltInAppearancePresets::all()));
        $ocean = BuiltInAppearancePresets::get('ocean');
        self::assertSame('light', $ocean->mode);
        self::assertSame(['accent' => '#0e7490', 'accent_deep' => '#155e75', 'danger' => '#b42318', 'warn' => '#b54708', 'paper' => '#f0f9fb', 'ink' => '#0c3a45', 'surface' => '#ffffff'], $ocean->light->toArray());
        self::assertSame(['accent' => '#fb923c', 'accent_deep' => '#fdba74', 'danger' => '#f97066', 'warn' => '#fdb022', 'paper' => '#120a06', 'ink' => '#ffedd5', 'surface' => '#1a100c'], BuiltInAppearancePresets::get('ember')->dark->toArray());
        self::assertSame('dark', BuiltInAppearancePresets::get('midnight')->mode);
        self::assertSame($ocean->dark, $ocean->palette('dark'));
        self::assertSame($ocean->light, $ocean->palette('light'));

        $this->expectException(\InvalidArgumentException::class);
        BuiltInAppearancePresets::get('beacon');
    }

    public function testPresetValidationAndConfigShape(): void
    {
        $preset = AppearancePreset::fromArray('brand', ['mode' => 'dark', 'light' => ['accent' => '#123456'], 'dark' => ['accent' => '#abcdef']]);
        self::assertSame('dark', $preset->mode);
        self::assertSame('#123456', $preset->light->accent);
        self::assertSame('#abcdef', $preset->dark->accent);
        self::assertSame(BuiltInAppearancePresets::get('slate')->dark->paper, $preset->dark->paper);
        self::assertSame('light', AppearancePreset::fromArray('x', [])->mode);

        try {
            new AppearancePreset('Bad Id', 'light', $preset->light, $preset->dark);
            self::fail('id');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Bad Id', $e->getMessage());
        }
        $this->expectException(\InvalidArgumentException::class);
        new AppearancePreset('ok', 'dim', $preset->light, $preset->dark);
    }

    public function testAppearanceCopiesAndArrayBridge(): void
    {
        $a = $this->appearance();
        self::assertSame(CornerStyle::Rounded, $a->corner());
        self::assertSame(BorderStrength::Strong, $a->border());
        self::assertSame($a->dark, $a->palette('dark'));
        self::assertSame($a->light, $a->palette('light'));

        $ember = BuiltInAppearancePresets::get('ember');
        $b = $a->withDark($ember->dark, 'ember')->withLight($ember->light);
        self::assertSame('ember', $b->themeIdDark);
        self::assertSame('custom', $b->themeId);
        self::assertSame($ember->light, $b->light);
        self::assertSame('Acme', $b->brandName);

        $c = $a->with(['brandName' => 'New', 'footerFixed' => false, 'light' => 'not a palette', 'cornerStyle' => ['x']]);
        self::assertSame('New', $c->brandName);
        self::assertFalse($c->footerFixed);
        self::assertSame($a->light, $c->light);
        self::assertSame('', $c->cornerStyle);

        $array = $a->toArray();
        self::assertSame(['brand_name', 'brand_eyebrow', 'light', 'dark', 'theme_id', 'theme_id_dark', 'corner_style', 'border_strength', 'footer_fixed'], array_keys($array));
        self::assertEquals($a, Appearance::fromArray($array, $b));
        $partial = Appearance::fromArray(['brand_name' => 'Only', 'light' => ['accent' => '#000000'], 'footer_fixed' => 0], $a);
        self::assertSame('Only', $partial->brandName);
        self::assertSame('#000000', $partial->light->accent);
        self::assertSame($a->light->ink, $partial->light->ink);
        self::assertSame($a->dark, $partial->dark);
        self::assertFalse($partial->footerFixed);
        self::assertSame($a->themeId, $partial->themeId);
    }

    public function testRegistryOrderOverridesApplyAndMatch(): void
    {
        $brand = AppearancePreset::fromArray('brand', ['light' => ['accent' => '#1f6f54']]);
        $override = AppearancePreset::fromArray('ocean', ['mode' => 'light', 'light' => ['accent' => '#000001']]);
        $provider = new class([AppearancePreset::fromArray('tagged', ['mode' => 'dark', 'dark' => ['accent' => '#00ff00']])]) implements AppearancePresetProviderInterface {
            /** @param list<AppearancePreset> $presets */
            public function __construct(private readonly array $presets)
            {
            }

            public function getPresets(): iterable
            {
                return $this->presets;
            }
        };

        $registry = new AppearancePresetRegistry([$brand, $override], [$provider], ['ocean', 'slate', 'midnight']);
        self::assertSame(['brand', 'ocean', 'tagged', 'slate', 'midnight'], $registry->ids());
        self::assertCount(5, $registry->all());
        self::assertSame('#000001', $registry->get('ocean')?->light->accent);
        self::assertFalse($registry->has('ember'));
        self::assertNull($registry->get('ember'));
        self::assertSame(['tagged', 'midnight'], array_map(static fn (AppearancePreset $p): string => $p->id, $registry->byMode('dark')));
        self::assertCount(7, (new AppearancePresetRegistry())->all());

        $a = $this->appearance();
        $applied = $registry->apply($a, 'brand');
        self::assertSame('brand', $applied->themeId);
        self::assertSame($brand->light, $applied->light);
        self::assertSame($a->dark, $applied->dark);
        $dark = $registry->apply($applied, 'midnight');
        self::assertSame('midnight', $dark->themeIdDark);
        self::assertEquals(BuiltInAppearancePresets::get('midnight')->dark, $dark->dark);
        self::assertSame($a, $registry->apply($a, 'nope'));

        self::assertSame('brand', $registry->matchLightId($dark));
        self::assertSame('midnight', $registry->matchDarkId($dark));
        self::assertSame('custom', $registry->matchLightId($a));
        self::assertSame('custom', $registry->matchDarkId($a));
    }

    public function testConfigProviderBuildsTheDefaults(): void
    {
        $registry = new AppearancePresetRegistry();
        $defaults = (new ConfigAppearanceProvider($registry))->getAppearance();
        self::assertSame('', $defaults->brandName);
        self::assertSame('slate', $defaults->themeId);
        self::assertSame('midnight', $defaults->themeIdDark);
        self::assertEquals(BuiltInAppearancePresets::get('slate')->light, $defaults->light);
        self::assertEquals(BuiltInAppearancePresets::get('midnight')->dark, $defaults->dark);
        self::assertSame('soft', $defaults->cornerStyle);
        self::assertSame('medium', $defaults->borderStrength);
        self::assertFalse($defaults->footerFixed);

        $custom = (new ConfigAppearanceProvider($registry, [
            'brand_name' => 'Acme',
            'brand_eyebrow' => 'Ops',
            'preset' => 'ocean',
            'preset_dark' => 'unknown',
            'corner_style' => 'sharp',
            'border_strength' => 'bogus',
            'footer_fixed' => true,
        ]))->getDefaults();
        self::assertSame('Acme', $custom->brandName);
        self::assertSame('ocean', $custom->themeId);
        self::assertSame('midnight', $custom->themeIdDark);
        self::assertSame('sharp', $custom->cornerStyle);
        self::assertSame('medium', $custom->borderStrength);
        self::assertTrue($custom->footerFixed);

        $fallback = (new ConfigAppearanceProvider($registry, ['preset' => 'unknown']))->getDefaults();
        self::assertSame('slate', $fallback->themeId);
    }

    private function appearance(): Appearance
    {
        return new Appearance(
            'Acme',
            'Ops',
            AppearancePalette::fromArray(['accent' => '#101010']),
            AppearancePalette::fromArray(['accent' => '#efefef'], BuiltInAppearancePresets::get('slate')->dark),
            'custom',
            'custom',
            'rounded',
            'strong',
            true,
        );
    }
}
