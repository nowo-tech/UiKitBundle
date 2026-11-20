<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Lint;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Demo style lint (2.60, `bin/nowo-ui-demo-style-lint`): a bundle demo consumes UiKit's demo platform styles only.
 * Reported in a demo app directory (e.g. `demo/symfony8`):
 *
 * - `own-stylesheet`: a `.css` / `.scss` / `.sass` / `.less` file of the demo (not under `vendor/`, `node_modules/`,
 *   `var/`, `public/bundles/` — assets the bundles ship —, `public/build/`, `e2e/`, `.data/`);
 * - `style-element`: a `<style` element in a Twig template;
 * - `style-attribute`: a `style="…"` attribute in a Twig template;
 * - `stylesheet-link`: a `<link rel="stylesheet">` in a Twig template whose href is not a bundle asset (`bundles/…`)
 *   or UiKit's own package (`'nowo_ui_kit'`);
 * - `host-tokens`: a template that prints a host theme or appearance style (`nowo_ui_tokens()`,
 *   `nowo_ui_theme_tokens()`, `nowo_ui_appearance_style()`): the demo platform shell owns its tokens.
 *
 * Twig comments are skipped; a line holding `nowo-ui-demo-style-lint-ignore` is skipped (say why next to it). Files
 * matching an allow-list glob (`fnmatch()` on the path relative to the demo directory) are skipped: the documented
 * exemptions only (a vendored third-party stylesheet, UiKit's own host-theme demo).
 */
final readonly class DemoStyleLint
{
    public const STYLESHEET_EXTENSIONS = ['css', 'scss', 'sass', 'less'];

    public const SKIP_DIRECTORIES = ['vendor', 'node_modules', 'var', 'public/bundles', 'public/build', 'e2e', '.data', '.git'];

    public const IGNORE_MARKER = 'nowo-ui-demo-style-lint-ignore';

    /**
     * @param list<string> $allow allow-list globs (relative paths)
     */
    public function __construct(
        private array $allow = [],
    ) {
    }

    /**
     * Findings of one template's contents (line numbers from 1).
     *
     * @return list<array{line: int, rule: string, match: string}>
     */
    public function lintTemplate(string $contents): array
    {
        $original = explode("\n", $contents);
        // Blank out Twig comments, keeping the line breaks so line numbers stay right.
        $contents = (string) preg_replace_callback('/\{#.*?#\}/s', static fn (array $m): string => preg_replace('/[^\n]/', ' ', $m[0]) ?? '', $contents);
        $findings = [];
        foreach (explode("\n", $contents) as $index => $line) {
            if (str_contains($original[$index] ?? '', self::IGNORE_MARKER)) {
                continue;
            }
            if (preg_match('/<style\b/i', $line, $m)) {
                $findings[] = ['line' => $index + 1, 'rule' => 'style-element', 'match' => $m[0]];
            }
            if (preg_match('/\sstyle\s*=\s*["\']/i', $line, $m)) {
                $findings[] = ['line' => $index + 1, 'rule' => 'style-attribute', 'match' => trim($m[0])];
            }
            if (preg_match('/\bnowo_ui_(?:theme_tokens|tokens|appearance_style)\s*\(/', $line, $m)) {
                $findings[] = ['line' => $index + 1, 'rule' => 'host-tokens', 'match' => $m[0]];
            }
            if (preg_match_all('/<link\b[^>]*\brel\s*=\s*["\']stylesheet["\'][^>]*>/i', $line, $links) > 0) {
                foreach ($links[0] as $link) {
                    if (!str_contains($link, 'bundles/') && !str_contains($link, "'nowo_ui_kit'")) {
                        $findings[] = ['line' => $index + 1, 'rule' => 'stylesheet-link', 'match' => $link];
                    }
                }
            }
        }

        return $findings;
    }

    /**
     * Findings per file (relative path => findings) of one demo app directory.
     *
     * @return array<string, list<array{line: int, rule: string, match: string}>>
     */
    public function lintDemo(string $directory): array
    {
        if (!is_dir($directory)) {
            throw new InvalidArgumentException(\sprintf('nowo-ui-demo-style-lint: %s is not a directory.', $directory));
        }
        $root = rtrim($directory, '/');
        $skip = self::SKIP_DIRECTORIES;
        $filter = static function (SplFileInfo $file) use ($root, $skip): bool {
            $relative = ltrim(substr($file->getPathname(), \strlen($root)), '/');

            return !$file->isDir() || !\in_array($relative, $skip, true);
        };
        $iterator = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), $filter));
        $results = [];
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $relative = ltrim(substr($file->getPathname(), \strlen($root)), '/');
            if ($this->allowed($relative)) {
                continue;
            }
            $extension = strtolower($file->getExtension());
            if (\in_array($extension, self::STYLESHEET_EXTENSIONS, true)) {
                $results[$relative] = [['line' => 1, 'rule' => 'own-stylesheet', 'match' => $relative]];
            } elseif ('twig' === $extension && str_starts_with($relative, 'templates/')) {
                $findings = $this->lintTemplate((string) file_get_contents($file->getPathname()));
                if ([] !== $findings) {
                    $results[$relative] = $findings;
                }
            }
        }
        ksort($results);

        return $results;
    }

    private function allowed(string $relative): bool
    {
        foreach ($this->allow as $glob) {
            if (fnmatch($glob, $relative)) {
                return true;
            }
        }

        return false;
    }
}
