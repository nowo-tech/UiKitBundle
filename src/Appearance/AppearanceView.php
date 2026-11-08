<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;
use Throwable;
use WeakReference;

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
 * Memo (audit IS-I1): with a request stack, the result — or the failure — is kept for the current main request only
 * (a weak reference to it), so a failing provider is asked and logged once per page, not once per template that reads
 * it. A new main request, or {@see self::reset()} (`kernel.reset`, long-running workers), drops it. Without a current
 * request every call asks the provider.
 */
final class AppearanceView implements ResetInterface
{
    /** @var WeakReference<Request>|null */
    private ?WeakReference $memoFor = null;

    private ?Appearance $memo = null;

    public function __construct(
        private readonly ?AppearanceProviderInterface $provider = null,
        private readonly bool $headLinks = false,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    public function reset(): void
    {
        $this->memoFor = null;
        $this->memo = null;
    }

    public function current(): ?Appearance
    {
        if (!$this->provider instanceof AppearanceProviderInterface) {
            return null;
        }
        $request = $this->requestStack?->getMainRequest();
        if ($request instanceof Request && $this->memoFor?->get() === $request) {
            return $this->memo;
        }
        try {
            $appearance = $this->provider->getAppearance();
        } catch (Throwable $e) {
            $this->logger?->warning('UiKit appearance: the appearance provider failed, the kit chrome uses its config.', ['exception' => $e]);
            $appearance = null;
        }
        if ($request instanceof Request) {
            $this->memoFor = WeakReference::create($request); // @igor-ignore — per-main-request memo, weak, dropped on reset()
            $this->memo = $appearance; // @igor-ignore — same memo
        }

        return $appearance;
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
