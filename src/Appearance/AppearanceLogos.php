<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Appearance;

use Nowo\UiKitBundle\Shell\SafeHref;

/**
 * Brand images of an {@see Appearance} (2.40, spec 220 S1): URLs only, never markup or bytes.
 *
 * Every source goes through {@see SafeHref::filterImage()} on construction (relative, `http` or `https`; anything else,
 * `javascript:` / `data:` included, becomes null), and the kit prints them only as `<img src>` / `<link href>`, so an SVG
 * behind a URL cannot run script in the page. `darkSrc` null means "the light logo on dark backgrounds too".
 */
final readonly class AppearanceLogos
{
    /** Horizontal lockup for light backgrounds (header, login, error pages). */
    public ?string $lightSrc;

    /** Same lockup for dark backgrounds; null = {@see self::$lightSrc}. */
    public ?string $darkSrc;

    /** Square brand mark (icons-only rail; the aside icon). */
    public ?string $markSrc;

    /** `<link rel="icon">` source of {@see AppearanceView::head()}. */
    public ?string $faviconSrc;

    /** `<link rel="apple-touch-icon">` source of {@see AppearanceView::head()}. */
    public ?string $appleTouchSrc;

    public function __construct(
        ?string $lightSrc = null,
        ?string $darkSrc = null,
        ?string $markSrc = null,
        ?string $faviconSrc = null,
        ?string $appleTouchSrc = null,
        /** Lower-case text form of the brand name ('' = none). */
        public string $wordmark = '',
    ) {
        $this->lightSrc = SafeHref::filterImage($lightSrc);
        $dark = SafeHref::filterImage($darkSrc);
        $this->darkSrc = $dark === $this->lightSrc ? null : $dark;
        $this->markSrc = SafeHref::filterImage($markSrc);
        $this->faviconSrc = SafeHref::filterImage($faviconSrc);
        $this->appleTouchSrc = SafeHref::filterImage($appleTouchSrc);
    }

    /**
     * Whether a logo lockup is set (the templates then print the light / dark `<img>` pair instead of the name).
     */
    public function hasLogo(): bool
    {
        return null !== $this->lightSrc;
    }

    /**
     * Whether no image source survived the filter (the wordmark does not count).
     */
    public function isEmpty(): bool
    {
        return null === $this->lightSrc && null === $this->markSrc && null === $this->faviconSrc && null === $this->appleTouchSrc;
    }
}
