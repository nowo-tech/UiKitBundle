<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

use Psr\Log\LoggerInterface;
use Throwable;

/**
 * What the kit templates read from the appearance SPI (2.40, spec 220 S1), registered whether the appearance is on or
 * off: {@see self::current()} never throws, so the shell, the guest / auth pages and the error layout (which must render
 * with a broken database: no 500 inside a 500) fall back to their config when the provider fails or is absent.
 *
 * - {@see self::current()} — the provider's {@see Appearance}, or null (appearance disabled, provider threw)
 * - {@see self::logos()} — its {@see AppearanceLogos}, or null
 * - {@see self::head()} — favicon / apple-touch `<link>` tags and `meta theme-color` per colour scheme (escaped)
 * - {@see self::headLinks()} — `nowo_ui_kit.appearance.head_links`: whether the kit layouts print {@see self::head()}
 *
 * Stateless (no memo): every call asks the provider, which memoises per request if it needs to.
 */
final readonly class AppearanceView
{
    public function __construct(
        private ?AppearanceProviderInterface $provider = null,
        private bool $headLinks = false,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function current(): ?Appearance
    {
        if (!$this->provider instanceof AppearanceProviderInterface) {
            return null;
        }
        try {
            return $this->provider->getAppearance();
        } catch (Throwable $e) {
            $this->logger?->warning('UiKit appearance: the appearance provider failed, the kit chrome uses its config.', ['exception' => $e]);

            return null;
        }
    }

    public function logos(): ?AppearanceLogos
    {
        return $this->current()?->logos;
    }

    public function headLinks(): bool
    {
        return $this->headLinks && $this->provider instanceof AppearanceProviderInterface;
    }

    /**
     * `<link rel="icon">`, `<link rel="apple-touch-icon">` (when the logos have them) and the two
     * `<meta name="theme-color" media="(prefers-color-scheme: …)">` (paper colour of each mode, when it is a CSS colour),
     * each on its own line indented for `<head>`. '' without an appearance.
     */
    public function head(): string
    {
        $appearance = $this->current();
        if (!$appearance instanceof Appearance) {
            return '';
        }
        $html = '';
        $logos = $appearance->logos;
        if (null !== $logos?->faviconSrc) {
            $html .= "\n    ".\sprintf('<link rel="icon" href="%s">', $this->escape($logos->faviconSrc));
        }
        if (null !== $logos?->appleTouchSrc) {
            $html .= "\n    ".\sprintf('<link rel="apple-touch-icon" href="%s">', $this->escape($logos->appleTouchSrc));
        }
        foreach ([AppearancePreset::MODE_LIGHT => $appearance->light, AppearancePreset::MODE_DARK => $appearance->dark] as $scheme => $palette) {
            if (AppearanceCssEmitter::isColor($palette->paper)) {
                $html .= "\n    ".\sprintf('<meta name="theme-color" content="%s" media="(prefers-color-scheme: %s)">', $this->escape(trim($palette->paper)), $scheme);
            }
        }

        return $html;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
