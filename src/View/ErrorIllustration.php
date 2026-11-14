<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

use InvalidArgumentException;

/**
 * Illustration of an error page.
 *
 * - From `nowo_ui_kit.error_pages.illustrations` (2.6): an asset path without extension, rendered as `<picture>` with a
 *   WebP `<source>` (when `webp`) and the `fallbackExtension` image (PNG by default), both through `asset()`.
 * - From an {@see ErrorIllustrationProviderInterface} (2.47): {@see self::fromUrl()} — a ready URL (`src`, never passed
 *   through `asset()`), optional `srcset` / `sizes` on the `<img>` and an optional WebP `<source srcset>`.
 *
 * Both carry intrinsic `width` / `height` so the card does not shift while the image loads. URLs are validated here
 * (http(s), protocol-relative, root-relative or relative; no other scheme, no whitespace or control characters) and
 * HTML-escaped by the template.
 */
final readonly class ErrorIllustration
{
    /**
     * @param string|null $src        2.47: URL of the `<img>` (set by {@see self::fromUrl()}); null = asset path mode
     * @param string|null $srcset     2.47: `srcset` of the `<img>` (URL mode only)
     * @param string|null $sizes      2.47: `sizes` of the `<img>` and the WebP `<source>` (URL mode only)
     * @param string|null $webpSrcset 2.47: `srcset` of the `<source type="image/webp">` (URL mode only)
     */
    public function __construct(
        public string $path,
        public int $width = 1024,
        public int $height = 683,
        public bool $webp = true,
        public string $fallbackExtension = 'png',
        public ?string $src = null,
        public ?string $srcset = null,
        public ?string $sizes = null,
        public ?string $webpSrcset = null,
    ) {
        if ('' === trim($path)) {
            throw new InvalidArgumentException('An error page illustration needs a non-empty asset path.');
        }
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException(\sprintf('Error page illustration "%s": width and height must be positive.', $path));
        }
        if (null !== $src) {
            $this->assertUrl($src);
        }
        foreach (['srcset' => $srcset, 'webpSrcset' => $webpSrcset, 'sizes' => $sizes] as $name => $value) {
            if (null !== $value && null === $src) {
                throw new InvalidArgumentException(\sprintf('Error page illustration "%s": %s needs the URL mode (ErrorIllustration::fromUrl()).', $path, $name));
            }
        }
        if (null !== $srcset) {
            $this->assertSrcset($srcset);
        }
        if (null !== $webpSrcset) {
            $this->assertSrcset($webpSrcset);
        }
        if (null !== $sizes && ('' === trim($sizes) || 1 !== preg_match('#^[a-zA-Z0-9\s(),.:%+*/-]+$#', $sizes))) {
            throw new InvalidArgumentException('Error page illustration: invalid sizes.');
        }
    }

    /**
     * Illustration from a ready URL (an {@see ErrorIllustrationProviderInterface}): `src` of the `<img>`, its optional
     * `srcset` (e.g. `/i/404-1x.png 1x, /i/404-2x.png 2x`) and `sizes`, and an optional WebP `<source srcset>`.
     *
     * @throws InvalidArgumentException on an empty / unsafe URL, a malformed srcset or non-positive dimensions
     */
    public static function fromUrl(string $src, int $width, int $height, ?string $webpSrcset = null, ?string $srcset = null, ?string $sizes = null): self
    {
        return new self($src, $width, $height, null !== $webpSrcset, 'png', $src, $srcset, $sizes, $webpSrcset);
    }

    /**
     * Whether this illustration comes from a ready URL ({@see self::fromUrl()}) rather than an asset path.
     */
    public function isUrl(): bool
    {
        return null !== $this->src;
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

    /**
     * An http(s), protocol-relative, root-relative or relative URL without whitespace or control characters.
     */
    private function assertUrl(string $url): void
    {
        if ('' === $url || 1 === preg_match('/[\x00-\x20\x7F]/', $url)) {
            throw new InvalidArgumentException('Error page illustration: a URL must be non-empty, without whitespace or control characters.');
        }
        if (1 === preg_match('#^([a-z][a-z0-9+.-]*):#i', $url, $m) && !\in_array(strtolower($m[1]), ['http', 'https'], true)) {
            throw new InvalidArgumentException(\sprintf('Error page illustration: the "%s" scheme is not allowed (http, https or a path).', $m[1]));
        }
    }

    /**
     * Image candidate strings (HTML `srcset`): `url [descriptor]` separated by commas; each URL as {@see self::assertUrl()},
     * each descriptor a width (`640w`) or a pixel density (`2x`, `1.5x`).
     */
    private function assertSrcset(string $srcset): void
    {
        $length = \strlen($srcset);
        $position = 0;
        $candidates = 0;
        while ($position < $length) {
            $position += strspn($srcset, " \t\n\r\f,", $position);
            if ($position >= $length) {
                break;
            }
            $size = strcspn($srcset, " \t\n\r\f", $position);
            $url = substr($srcset, $position, $size);
            $position += $size;
            ++$candidates;
            if (str_ends_with($url, ',')) {
                $this->assertUrl(rtrim($url, ','));

                continue;
            }
            $this->assertUrl($url);
            $comma = strpos($srcset, ',', $position);
            $end = false === $comma ? $length : $comma;
            $descriptor = trim(substr($srcset, $position, $end - $position));
            if ('' !== $descriptor && 1 !== preg_match('/^(?:\d+w|\d+(?:\.\d+)?x)$/', $descriptor)) {
                throw new InvalidArgumentException(\sprintf('Error page illustration: invalid srcset descriptor "%s".', $descriptor));
            }
            $position = $end + 1;
        }
        if (0 === $candidates) {
            throw new InvalidArgumentException('Error page illustration: an empty srcset.');
        }
    }
}
