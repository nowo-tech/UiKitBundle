<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

/**
 * Default provider: the appearance described by `nowo_ui_kit.appearance.defaults` (no storage).
 *
 * Also the fallback hosts can use for "reset to defaults" ({@see self::getDefaults()}).
 */
final readonly class ConfigAppearanceProvider implements AppearanceProviderInterface
{
    /**
     * @param array{brand_name?: string, brand_eyebrow?: string, preset?: string, preset_dark?: string, corner_style?: string, border_strength?: string, footer_fixed?: bool} $defaults
     */
    public function __construct(
        private AppearancePresetRegistry $registry,
        private array $defaults = [],
    ) {
    }

    public function getAppearance(): Appearance
    {
        return $this->getDefaults();
    }

    public function getDefaults(): Appearance
    {
        $lightId = $this->defaults['preset'] ?? 'slate';
        $darkId = $this->defaults['preset_dark'] ?? 'midnight';
        $light = $this->registry->get($lightId) ?? BuiltInAppearancePresets::get('slate');
        $dark = $this->registry->get($darkId) ?? BuiltInAppearancePresets::get('midnight');

        return new Appearance(
            $this->defaults['brand_name'] ?? '',
            $this->defaults['brand_eyebrow'] ?? '',
            $light->light,
            $dark->dark,
            $light->id,
            $dark->id,
            CornerStyle::fromLoose($this->defaults['corner_style'] ?? null)->value,
            BorderStrength::fromLoose($this->defaults['border_strength'] ?? null)->value,
            $this->defaults['footer_fixed'] ?? false,
        );
    }
}
