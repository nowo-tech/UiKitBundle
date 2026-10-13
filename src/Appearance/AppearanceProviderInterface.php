<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

/**
 * Host storage SPI: returns the current site appearance (Doctrine entity, settings row, config…).
 *
 * Point `nowo_ui_kit.appearance.provider` at your service. Without one, the kit uses
 * {@see ConfigAppearanceProvider} (the `appearance.defaults` config). Implementations that cache per
 * request should implement `Symfony\Contracts\Service\ResetInterface` (long-running workers).
 */
interface AppearanceProviderInterface
{
    public function getAppearance(): Appearance;
}
