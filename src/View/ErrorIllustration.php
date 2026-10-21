<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Illustration of an error page (2.6, `nowo_ui_kit.error_pages.illustrations`): an asset path without extension,
 * rendered as `<picture>` with a WebP `<source>` (when `webp`) and the `fallbackExtension` image (PNG by default),
 * with intrinsic `width` / `height` so the card does not shift while the image loads.
 */
final readonly class ErrorIllustration
{
    public function __construct(
        public string $path,
        public int $width = 1024,
        public int $height = 683,
        public bool $webp = true,
        public string $fallbackExtension = 'png',
    ) {
        if ('' === trim($path)) {
            throw new \InvalidArgumentException('An error page illustration needs a non-empty asset path.');
        }
        if ($width < 1 || $height < 1) {
            throw new \InvalidArgumentException(\sprintf('Error page illustration "%s": width and height must be positive.', $path));
        }
    }

    /**
     * Asset path of the WebP source (`<path>.webp`).
     */
    public function webpSrc(): string
    {
        return $this->path.'.webp';
    }

    /**
     * Asset path of the `<img>` fallback (`<path>.<fallbackExtension>`).
     */
    public function fallbackSrc(): string
    {
        return $this->path.'.'.ltrim($this->fallbackExtension, '.');
    }
}
