<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

use Nowo\Brand\Contract\Brand;
use Nowo\Brand\Contract\BrandAssetSlot;
use Nowo\Brand\Contract\BrandColors;
use Nowo\Brand\Contract\BrandProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Default appearance provider when the host has a `nowo-tech/brand-contracts` {@see BrandProviderInterface} service
 * and left `nowo_ui_kit.appearance.provider` null (2.40, spec 220 S1; wired by `BrandAppearanceProviderPass`).
 *
 * From the brand: name, eyebrow (a translatable one in the translator's current locale), the seven colours of each
 * mode and the logo URLs (after the contract's fallback chain). From `appearance.defaults` (the
 * {@see ConfigAppearanceProvider}): corner style, border strength, fixed footer, and every value when the brand
 * provider throws (the error is logged; a page, an error page above all, never fails because of the brand). The
 * preset ids are the configured defaults when the brand colours equal them, else {@see Appearance::CUSTOM}.
 *
 * Stateless: each call asks the brand provider again (providers memoise per request themselves), so a long-running
 * worker keeps nothing between requests.
 */
final readonly class BrandAppearanceProvider implements AppearanceProviderInterface
{
    private TranslatorInterface $translator;

    public function __construct(
        private ConfigAppearanceProvider $config,
        private BrandProviderInterface $brands,
        ?TranslatorInterface $translator = null,
        private ?LoggerInterface $logger = null,
    ) {
        $this->translator = $translator ?? new IdentityTranslator();
    }

    public function getAppearance(): Appearance
    {
        $defaults = $this->config->getDefaults();
        try {
            $brand = $this->brands->getBrand();

            return $this->fromBrand($brand, $defaults);
        } catch (Throwable $e) {
            $this->logger?->warning('UiKit appearance: the brand provider failed, using the appearance defaults.', ['exception' => $e]);

            return $defaults;
        }
    }

    private function fromBrand(Brand $brand, Appearance $defaults): Appearance
    {
        $light = self::palette($brand->light);
        $dark = self::palette($brand->dark);
        $url = static fn (string $slot): ?string => $brand->asset($slot)?->url;

        return new Appearance(
            $brand->name,
            $brand->eyebrowText($this->translator) ?? '',
            $light,
            $dark,
            $light->equals($defaults->light) ? $defaults->themeId : Appearance::CUSTOM,
            $dark->equals($defaults->dark) ? $defaults->themeIdDark : Appearance::CUSTOM,
            $defaults->cornerStyle,
            $defaults->borderStrength,
            $defaults->footerFixed,
            new AppearanceLogos(
                $url(BrandAssetSlot::LOGO_LIGHT),
                $url(BrandAssetSlot::LOGO_DARK),
                $url(BrandAssetSlot::MARK),
                $url(BrandAssetSlot::FAVICON),
                $url(BrandAssetSlot::APP_ICON),
                $brand->wordmark,
            ),
        );
    }

    /**
     * Contract colour names → UiKit palette keys (primary → accent, warning → warn, text → ink).
     */
    public static function palette(BrandColors $colors): AppearancePalette
    {
        return new AppearancePalette(
            $colors->primary,
            $colors->primaryDeep,
            $colors->danger,
            $colors->warning,
            $colors->paper,
            $colors->text,
            $colors->surface,
        );
    }
}
