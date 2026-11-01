<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Theme;

/**
 * `<link rel="preload" as="font" crossorigin>` tags for the fonts a page paints above the fold (2.21,
 * `nowo_ui_kit.font_preload`, Twig `nowo_ui_font_preload()`; the document and error layouts print them in <head>), so
 * text does not wait for the stylesheet to discover its fonts.
 *
 * Two sources, combined in this order:
 * - `fonts`: font URLs as they are served (`/fonts/inter-400.woff2`), printed as given;
 * - `manifest`: a Vite manifest (`.vite/manifest.json`) — the assets of `entry` whose file name starts with one of
 *   `prefixes` (hashed builds: `montserrat-latin-400-normal-<hash>.woff2`), one tag per prefix, prefixed with
 *   `base`. A missing or broken manifest (dev server, no build yet) prints nothing.
 *
 * The MIME type comes from the extension (woff2, woff, ttf, otf). Font preloads are governed by the CSP `font-src`
 * directive, not by a nonce, so the tags carry none.
 *
 * FrankenPHP worker mode: the manifest tags are memoized per process, keyed by the manifest's mtime + size, so the
 * file is decoded once per build instead of on every render. The memo derives from a build file only (no request or
 * user data); a new build (new mtime or size) replaces it on the next call.
 */
final class FontPreload
{
    private const TYPES = ['woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf'];

    /** @var array{fingerprint: string, html: string}|null tags rendered from the manifest whose `mtime:size` is `fingerprint` */
    private ?array $memo = null;

    /**
     * @param list<string> $fonts    font URLs
     * @param list<string> $prefixes manifest asset basename prefixes
     */
    public function __construct(
        private readonly array $fonts = [],
        private readonly ?string $manifestPath = null,
        private readonly string $entry = 'assets/app.js',
        private readonly array $prefixes = [],
        private readonly string $base = '/build/',
    ) {
    }

    /**
     * Whether anything is configured (no config: the layouts print nothing, byte-identical to 2.20).
     */
    public function isEnabled(): bool
    {
        return [] !== $this->fonts || (null !== $this->manifestPath && [] !== $this->prefixes);
    }

    public function tags(): string
    {
        $html = '';
        foreach ($this->fonts as $url) {
            $html .= $this->tag($url);
        }

        return $html.$this->manifestTags();
    }

    private function manifestTags(): string
    {
        if (null === $this->manifestPath || [] === $this->prefixes) {
            return '';
        }
        // Long-lived worker: never trust PHP's stat cache for a file a deploy may replace.
        clearstatcache(true, $this->manifestPath);
        if (!is_file($this->manifestPath)) {
            return '';
        }
        $fingerprint = filemtime($this->manifestPath).':'.filesize($this->manifestPath);
        if (null === $this->memo || $fingerprint !== $this->memo['fingerprint']) {
            $this->memo = ['fingerprint' => $fingerprint, 'html' => $this->renderManifest($this->manifestPath)]; // @igor-ignore — per-process memo of a build file keyed by its mtime:size; no request / user data
        }

        return $this->memo['html'];
    }

    private function renderManifest(string $path): string
    {
        try {
            $manifest = json_decode((string) file_get_contents($path), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return '';
        }
        $assets = \is_array($manifest) && \is_array($manifest[$this->entry]['assets'] ?? null) ? $manifest[$this->entry]['assets'] : [];

        $html = '';
        foreach ($this->prefixes as $prefix) {
            foreach ($assets as $asset) {
                if (\is_string($asset) && 1 === preg_match('#(?:^|/)([^/]+)\.(?:woff2|woff|ttf|otf)$#', $asset, $m) && str_starts_with($m[1], $prefix)) {
                    $html .= $this->tag($this->base.$asset);
                    break;
                }
            }
        }

        return $html;
    }

    private function tag(string $url): string
    {
        $extension = strtolower(pathinfo((string) parse_url($url, \PHP_URL_PATH), \PATHINFO_EXTENSION));
        $type = self::TYPES[$extension] ?? null;

        return \sprintf(
            '<link rel="preload" href="%s" as="font"%s crossorigin>',
            htmlspecialchars($url, \ENT_QUOTES),
            null === $type ? '' : ' type="'.$type.'"',
        );
    }
}
