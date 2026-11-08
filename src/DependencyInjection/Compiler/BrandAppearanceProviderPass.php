<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\DependencyInjection\Compiler;

use Nowo\Brand\Contract\BrandProviderInterface;
use Nowo\UiKitBundle\Appearance\BrandAppearanceProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * 2.40 (spec 220 S1): points `nowo_ui_kit.appearance.provider` at {@see BrandAppearanceProvider}
 * when every condition holds, and otherwise removes that candidate service (the container is then the 2.39 one):
 *
 * - the appearance is enabled with `provider: null` and `brand: auto` (the extension registered the candidate);
 * - a {@see BrandProviderInterface} service or alias exists (the host's, or InstanceSettings' bridge);
 * - the provider alias still targets the config provider, i.e. no other pass (InstanceSettings' bridge, which reads
 *   the stored corner / border / footer too) has re-aliased it. Whichever order the passes run in, the bridge wins.
 */
final class BrandAppearanceProviderPass implements CompilerPassInterface
{
    public const CANDIDATE = 'nowo_ui_kit.appearance.brand_provider';

    public const PROVIDER_ALIAS = 'nowo_ui_kit.appearance.provider';

    public const CONFIG_PROVIDER = 'nowo_ui_kit.appearance.config_provider';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::CANDIDATE)) {
            return;
        }
        $brand = BrandProviderInterface::class;
        $hasBrand = $container->hasDefinition($brand) || $container->hasAlias($brand);
        $untouched = $container->hasAlias(self::PROVIDER_ALIAS)
            && self::CONFIG_PROVIDER === (string) $container->getAlias(self::PROVIDER_ALIAS);

        if ($hasBrand && $untouched) {
            $public = $container->getAlias(self::PROVIDER_ALIAS)->isPublic();
            $container->setAlias(self::PROVIDER_ALIAS, self::CANDIDATE)->setPublic($public);

            return;
        }
        $container->removeDefinition(self::CANDIDATE);
    }
}
