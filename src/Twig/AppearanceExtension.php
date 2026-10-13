<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Twig;

use Nowo\UiKitBundle\Appearance\Appearance;
use Nowo\UiKitBundle\Appearance\AppearanceCssEmitter;
use Nowo\UiKitBundle\Appearance\AppearancePreset;
use Nowo\UiKitBundle\Appearance\AppearancePresetRegistry;
use Nowo\UiKitBundle\Appearance\AppearanceProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig access to the appearance SPI (registered only with `nowo_ui_kit.appearance.enabled`).
 *
 * - `nowo_ui_appearance()` — the current {@see Appearance} (brand name, palettes, flags)
 * - `nowo_ui_appearance_css()` — the token stylesheet text
 * - `nowo_ui_appearance_style(nonce = null)` — `<style nonce data-nowo-ui-appearance>…</style>`; the nonce
 *   defaults to the request attribute `csp_nonce`
 * - `nowo_ui_appearance_presets(mode = null)` — presets for pickers
 */
final class AppearanceExtension extends AbstractExtension
{
    public function __construct(
        private readonly AppearanceProviderInterface $provider,
        private readonly AppearanceCssEmitter $emitter,
        private readonly AppearancePresetRegistry $registry,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_ui_appearance', $this->appearance(...)),
            new TwigFunction('nowo_ui_appearance_css', $this->css(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_ui_appearance_style', $this->style(...), ['is_safe' => ['html']]),
            new TwigFunction('nowo_ui_appearance_presets', $this->presets(...)),
        ];
    }

    public function appearance(): Appearance
    {
        return $this->provider->getAppearance();
    }

    public function css(): string
    {
        return $this->emitter->emit($this->provider->getAppearance());
    }

    public function style(?string $nonce = null): string
    {
        $nonce ??= $this->requestNonce();
        $attr = null !== $nonce && '' !== $nonce ? ' nonce="'.htmlspecialchars($nonce, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8').'"' : '';

        return '<style'.$attr.' data-nowo-ui-appearance>'."\n".$this->css()."\n".'</style>';
    }

    /**
     * @return list<AppearancePreset>
     */
    public function presets(?string $mode = null): array
    {
        return null === $mode ? $this->registry->all() : $this->registry->byMode($mode);
    }

    private function requestNonce(): ?string
    {
        $nonce = $this->requestStack?->getCurrentRequest()?->attributes->get('csp_nonce');

        return \is_string($nonce) ? $nonce : null;
    }
}
