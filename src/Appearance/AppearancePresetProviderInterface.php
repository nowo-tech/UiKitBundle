<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

/**
 * Contributes host presets to the {@see AppearancePresetRegistry}. Autoconfigured with the tag
 * `nowo_ui_kit.appearance_preset_provider` (or tag it yourself).
 */
interface AppearancePresetProviderInterface
{
    public const TAG = 'nowo_ui_kit.appearance_preset_provider';

    /**
     * @return iterable<AppearancePreset>
     */
    public function getPresets(): iterable;
}
