<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Appearance;

use Nowo\Brand\Contract\Brand;
use Nowo\Brand\Contract\BrandAsset;
use Nowo\Brand\Contract\BrandAssetOrigin;
use Nowo\Brand\Contract\BrandAssetSlot;
use Nowo\Brand\Contract\BrandColors;
use Nowo\Brand\Contract\BrandProviderInterface;
use Nowo\UiKitBundle\Appearance\Appearance;
use Nowo\UiKitBundle\Appearance\AppearanceLogos;
use Nowo\UiKitBundle\Appearance\AppearancePalette;
use Nowo\UiKitBundle\Appearance\AppearancePresetRegistry;
use Nowo\UiKitBundle\Appearance\AppearanceProviderInterface;
use Nowo\UiKitBundle\Appearance\AppearanceView;
use Nowo\UiKitBundle\Appearance\BrandAppearanceProvider;
use Nowo\UiKitBundle\Appearance\BuiltInAppearancePresets;
use Nowo\UiKitBundle\Appearance\ConfigAppearanceProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Component\Translation\Translator;

/**
 * 2.40 (spec 220 S1): AppearanceLogos, the brand-driven default provider, and the never-throwing AppearanceView.
 */
final class BrandAppearanceTest extends TestCase
{
    public const BEACON_LIGHT = ['primary' => '#1f6f54', 'primaryDeep' => '#134736', 'danger' => '#b42318', 'warning' => '#b54708', 'paper' => '#f3f6f4', 'text' => '#0f1c18', 'surface' => '#ffffff'];

    public const BEACON_DARK = ['primary' => '#4aad7f', 'primaryDeep' => '#6bc49a', 'danger' => '#f97066', 'warning' => '#fdb022', 'paper' => '#0c1210', 'text' => '#e6eee9', 'surface' => '#151c19'];

    public function testLogosKeepOnlySafeImageSources(): void
    {
        $logos = new AppearanceLogos('/brand/light.png', 'javascript:alert(1)', 'data:image/svg+xml,<svg onload=x>', "java\tscript:x", 'https://cdn.example/icon.png', 'acme');
        self::assertSame('/brand/light.png', $logos->lightSrc);
        self::assertNull($logos->darkSrc);
        self::assertNull($logos->markSrc);
        self::assertNull($logos->faviconSrc);
        self::assertSame('https://cdn.example/icon.png', $logos->appleTouchSrc);
        self::assertSame('acme', $logos->wordmark);
        self::assertTrue($logos->hasLogo());
        self::assertFalse($logos->isEmpty());

        $same = new AppearanceLogos('/a.png', '/a.png');
        self::assertNull($same->darkSrc, 'a dark logo equal to the light one is not a pair');
        self::assertSame('/b.png', (new AppearanceLogos('/a.png', '/b.png'))->darkSrc);

        $empty = new AppearanceLogos(markSrc: 'vbscript:x');
        self::assertFalse($empty->hasLogo());
        self::assertTrue($empty->isEmpty());
        self::assertFalse((new AppearanceLogos(markSrc: '/m.png'))->isEmpty());
        self::assertFalse((new AppearanceLogos(faviconSrc: '/f.png'))->isEmpty());
        self::assertFalse((new AppearanceLogos(appleTouchSrc: '/t.png'))->isEmpty());
    }

    public function testAppearanceCarriesLogosThroughWithAndFromArray(): void
    {
        $logos = new AppearanceLogos('/l.png');
        $preset = BuiltInAppearancePresets::get('slate');
        $appearance = new Appearance('Acme', '', $preset->light, $preset->dark, logos: $logos);

        self::assertSame($logos, $appearance->with(['brandName' => 'B'])->logos);
        self::assertNull($appearance->with(['logos' => null])->logos);
        self::assertNull($appearance->with(['logos' => 'not logos'])->logos);
        $other = new AppearanceLogos('/o.png');
        self::assertSame($other, $appearance->with(['logos' => $other])->logos);
        self::assertSame($logos, Appearance::fromArray(['brand_name' => 'X'], $appearance)->logos);
        self::assertArrayNotHasKey('logos', $appearance->toArray(), 'forms and stored rows do not carry logos');
        self::assertNull((new Appearance('A', '', $preset->light, $preset->dark))->logos);
    }

    public function testBrandDrivesNameEyebrowColoursAndLogos(): void
    {
        $brand = new Brand(
            'Acme Billing',
            BrandColors::fromArray(self::BEACON_LIGHT),
            BrandColors::fromArray(self::BEACON_DARK),
            [
                BrandAssetSlot::LOGO_LIGHT => self::asset(BrandAssetSlot::LOGO_LIGHT, '/brand/logo-light.svg', 'image/svg+xml', BrandAssetOrigin::ConfigDefault),
                BrandAssetSlot::MARK => self::asset(BrandAssetSlot::MARK, '/_settings-file/brand/mark/abc123abc123.png'),
                BrandAssetSlot::APP_ICON => self::asset(BrandAssetSlot::APP_ICON, '/_settings-file/brand/app_icon/def456def456-180.png'),
            ],
            eyebrow: 'Billing',
            wordmark: 'acme',
        );
        $provider = new BrandAppearanceProvider($this->config(['corner_style' => 'sharp', 'border_strength' => 'strong', 'footer_fixed' => true]), self::brands($brand));

        $appearance = $provider->getAppearance();
        self::assertSame('Acme Billing', $appearance->brandName);
        self::assertSame('Billing', $appearance->brandEyebrow);
        self::assertSame('#1f6f54', $appearance->light->accent);
        self::assertSame('#134736', $appearance->light->accentDeep);
        self::assertSame('#b54708', $appearance->light->warn);
        self::assertSame('#0f1c18', $appearance->light->ink);
        self::assertSame('#151c19', $appearance->dark->surface);
        self::assertSame(Appearance::CUSTOM, $appearance->themeId);
        self::assertSame(Appearance::CUSTOM, $appearance->themeIdDark);
        self::assertSame('sharp', $appearance->cornerStyle, 'corner, border and footer stay from the defaults');
        self::assertSame('strong', $appearance->borderStrength);
        self::assertTrue($appearance->footerFixed);

        $logos = $appearance->logos;
        self::assertInstanceOf(AppearanceLogos::class, $logos);
        self::assertSame('/brand/logo-light.svg', $logos->lightSrc);
        self::assertNull($logos->darkSrc, 'logo_dark falls back to logo_light: one <img>');
        self::assertSame('/_settings-file/brand/mark/abc123abc123.png', $logos->markSrc);
        self::assertSame('/_settings-file/brand/mark/abc123abc123.png', $logos->faviconSrc, 'favicon → mark');
        self::assertSame('/_settings-file/brand/app_icon/def456def456-180.png', $logos->appleTouchSrc);
        self::assertSame('acme', $logos->wordmark);
    }

    public function testPresetIdsAreKeptWhenTheBrandColoursEqualTheDefaults(): void
    {
        $slate = BuiltInAppearancePresets::get('slate')->light;
        $midnight = BuiltInAppearancePresets::get('midnight')->dark;
        $colors = static fn ($p): BrandColors => new BrandColors(strtoupper($p->accent), $p->accentDeep, $p->danger, $p->warn, $p->paper, $p->ink, $p->surface);
        $brand = new Brand('Acme', $colors($slate), $colors($midnight));

        $appearance = (new BrandAppearanceProvider($this->config(), self::brands($brand)))->getAppearance();
        self::assertSame('slate', $appearance->themeId);
        self::assertSame('midnight', $appearance->themeIdDark);
        self::assertSame('', $appearance->brandEyebrow, 'no eyebrow = empty');
        self::assertFalse($appearance->logos?->hasLogo());
        self::assertTrue($appearance->logos?->isEmpty());
        self::assertSame('acme', $appearance->logos?->wordmark);
    }

    public function testTranslatableEyebrowFollowsTheTranslatorLocale(): void
    {
        $translator = new Translator('es');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['brand.eyebrow' => 'Panel'], 'es', 'messages');
        $translator->addResource('array', ['brand.eyebrow' => 'Dashboard'], 'en', 'messages');
        $brand = new Brand('Acme', BrandColors::fromArray(self::BEACON_LIGHT), BrandColors::fromArray(self::BEACON_DARK), eyebrow: new TranslatableMessage('brand.eyebrow'));
        $provider = new BrandAppearanceProvider($this->config(), self::brands($brand), $translator);

        self::assertSame('Panel', $provider->getAppearance()->brandEyebrow);
        $translator->setLocale('en');
        self::assertSame('Dashboard', $provider->getAppearance()->brandEyebrow);
        self::assertSame('brand.eyebrow', (new BrandAppearanceProvider($this->config(), self::brands($brand)))->getAppearance()->brandEyebrow, 'identity translator without one');
    }

    public function testAFailingBrandProviderFallsBackToTheDefaultsAndLogs(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = $level.': '.$message.' '.($context['exception'] instanceof RuntimeException ? 'with exception' : '');
            }
        };
        $config = $this->config(['brand_name' => 'Config name']);
        $failing = new class implements BrandProviderInterface {
            public function getBrand(): Brand
            {
                throw new RuntimeException('database down');
            }
        };

        $appearance = (new BrandAppearanceProvider($config, $failing, logger: $logger))->getAppearance();
        self::assertEquals($config->getDefaults(), $appearance);
        self::assertSame(['warning: UiKit appearance: the brand provider failed, using the appearance defaults. with exception'], $logger->messages);
        self::assertEquals($config->getDefaults(), (new BrandAppearanceProvider($config, $failing))->getAppearance(), 'no logger');
    }

    public function testAppearanceViewNeverThrowsAndEscapesTheHead(): void
    {
        $none = new AppearanceView();
        self::assertNull($none->current());
        self::assertNull($none->logos());
        self::assertSame('', $none->head());
        self::assertFalse($none->headLinks());
        self::assertFalse((new AppearanceView(null, true))->headLinks(), 'no appearance: nothing to print');

        $preset = BuiltInAppearancePresets::get('slate');
        $logos = new AppearanceLogos('/l.png', faviconSrc: '/f.png?v=1&x="y"', appleTouchSrc: '/t.png');
        $appearance = new Appearance('A', '', AppearancePalette::fromArray(['paper' => '#f8fafc'], $preset->light), BuiltInAppearancePresets::get('midnight')->dark, logos: $logos);
        $view = new AppearanceView(self::fixed($appearance), true);
        self::assertSame($appearance, $view->current());
        self::assertSame($logos, $view->logos());
        self::assertTrue($view->headLinks());
        self::assertSame(
            "\n    <link rel=\"icon\" href=\"/f.png?v=1&amp;x=&quot;y&quot;\">"
            ."\n    <link rel=\"apple-touch-icon\" href=\"/t.png\">"
            ."\n    <meta name=\"theme-color\" content=\"#f8fafc\" media=\"(prefers-color-scheme: light)\">"
            ."\n    <meta name=\"theme-color\" content=\"".BuiltInAppearancePresets::get('midnight')->dark->paper.'" media="(prefers-color-scheme: dark)">',
            $view->head(),
        );
        self::assertFalse((new AppearanceView(self::fixed($appearance)))->headLinks());

        $bad = new Appearance('A', '', AppearancePalette::fromArray(['paper' => 'red;}</style>'], $preset->light), $preset->dark);
        self::assertStringNotContainsString('red;', (new AppearanceView(self::fixed($bad)))->head(), 'a colour that is not one is dropped');
        self::assertStringNotContainsString('rel="icon"', (new AppearanceView(self::fixed($bad)))->head(), 'no logos: only theme-color');

        $logger = new class extends AbstractLogger {
            public int $count = 0;

            public function log($level, string|Stringable $message, array $context = []): void
            {
                ++$this->count;
            }
        };
        $throwing = new class implements AppearanceProviderInterface {
            public function getAppearance(): Appearance
            {
                throw new RuntimeException('no database');
            }
        };
        $failing = new AppearanceView($throwing, true, $logger);
        self::assertNull($failing->current());
        self::assertNull($failing->logos());
        self::assertSame('', $failing->head());
        self::assertSame(3, $logger->count);
        self::assertNull((new AppearanceView($throwing))->current(), 'no logger');
    }

    public static function asset(string $slot, string $url, string $type = 'image/png', BrandAssetOrigin $origin = BrandAssetOrigin::Uploaded): BrandAsset
    {
        return new BrandAsset($slot, $url, $type, 512, 512, str_repeat('a', 64), $origin);
    }

    public static function brands(Brand $brand): BrandProviderInterface
    {
        return new class($brand) implements BrandProviderInterface {
            public function __construct(private readonly Brand $brand)
            {
            }

            public function getBrand(): Brand
            {
                return $this->brand;
            }
        };
    }

    public static function fixed(Appearance $appearance): AppearanceProviderInterface
    {
        return new class($appearance) implements AppearanceProviderInterface {
            public function __construct(private readonly Appearance $appearance)
            {
            }

            public function getAppearance(): Appearance
            {
                return $this->appearance;
            }
        };
    }

    /**
     * @param array{brand_name?: string, brand_eyebrow?: string, preset?: string, preset_dark?: string, corner_style?: string, border_strength?: string, footer_fixed?: bool} $defaults
     */
    private function config(array $defaults = []): ConfigAppearanceProvider
    {
        return new ConfigAppearanceProvider(new AppearancePresetRegistry(), $defaults);
    }
}
