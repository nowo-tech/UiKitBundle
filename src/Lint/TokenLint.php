<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Lint;

use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Colour-literal lint of bundle CSS, SCSS and Twig (2.40, spec 220 §2.1; `bin/nowo-ui-token-lint`): bundle UIs read
 * colours only through `--nowo-ui-*` tokens, so a literal `#rgb` / `#rrggbb` / `#rrggbbaa` or an `rgb(` / `rgba(` is
 * reported, except:
 *
 * - inside the fallback of a `var(--…, …)` (`var(--nowo-ui-primary, #2563eb)` is the documented pattern);
 * - `rgb(var(--nowo-ui-…) / …)` (the shadow channels token);
 * - in comments (CSS block comments, Twig comments) and on a line holding `nowo-ui-token-lint-ignore`;
 * - in files matching an allow-list glob (`fnmatch()` against the path as given; default: UiKit's own token defaults,
 *   `nowo-ui.css` and `nowo-ui.unlayered.css`).
 *
 * A heuristic, not a parser: an id selector or a fragment link that spells a hex colour (`#add`, `href="#bad"`) is
 * reported too; ignore that line or allow-list the file.
 */
final readonly class TokenLint
{
    public const EXTENSIONS = ['css', 'scss', 'twig'];

    public const DEFAULT_ALLOW = ['*/nowo-ui.css', '*/nowo-ui.unlayered.css', 'nowo-ui.css', 'nowo-ui.unlayered.css'];

    public const IGNORE_MARKER = 'nowo-ui-token-lint-ignore';

    private const LITERAL = '/(?<![\w&#-])#(?:[0-9a-fA-F]{8}|[0-9a-fA-F]{6}|[0-9a-fA-F]{3,4})(?![\w-])|\brgba?\((?!\s*var\(--nowo-ui-)/';

    /**
     * @param list<string> $allow allow-list globs
     */
    public function __construct(
        private array $allow = self::DEFAULT_ALLOW,
    ) {
    }

    /**
     * Findings of one file's contents (line numbers from 1).
     *
     * @return list<array{line: int, match: string}>
     */
    public function lintContents(string $contents): array
    {
        $original = explode("\n", $contents);
        $lines = explode("\n", $this->withoutFallbacks($this->withoutComments($contents)));
        $findings = [];
        foreach ($lines as $index => $line) {
            if (str_contains($original[$index] ?? '', self::IGNORE_MARKER)) {
                continue;
            }
            if (preg_match_all(self::LITERAL, $line, $matches) > 0) {
                foreach ($matches[0] as $match) {
                    $findings[] = ['line' => $index + 1, 'match' => $match];
                }
            }
        }

        return $findings;
    }

    /**
     * Findings of every CSS / SCSS / Twig file under the given files or directories, by path (sorted).
     *
     * @param list<string> $paths
     *
     * @return array<string, list<array{line: int, match: string}>>
     *
     * @throws InvalidArgumentException when a path does not exist (a typo must not pass as "clean", audit IS-I7)
     */
    public function lintPaths(array $paths): array
    {
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                throw new InvalidArgumentException(\sprintf('nowo-ui-token-lint: path "%s" does not exist.', $path));
            }
        }
        $results = [];
        foreach ($paths as $path) {
            foreach ($this->files($path) as $file) {
                if ($this->isAllowed($file)) {
                    continue;
                }
                $contents = file_get_contents($file);
                $findings = false === $contents ? [] : $this->lintContents($contents);
                if ([] !== $findings) {
                    $results[$file] = $findings;
                }
            }
        }
        ksort($results);

        return $results;
    }

    public function isAllowed(string $path): bool
    {
        foreach ($this->allow as $glob) {
            if (fnmatch($glob, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function files(string $path): array
    {
        if (is_file($path)) {
            return $this->linted($path) ? [$path] : [];
        }
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $this->linted($file->getPathname())) {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    private function linted(string $path): bool
    {
        return \in_array(strtolower(pathinfo($path, \PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }

    /**
     * Blanks CSS block comments and Twig comments, keeping their line breaks (line numbers stay right).
     */
    private function withoutComments(string $contents): string
    {
        return (string) preg_replace_callback(
            '~/\*.*?\*/|\{#.*?#\}~s',
            static fn (array $m): string => str_repeat("\n", substr_count($m[0], "\n")),
            $contents,
        );
    }

    /**
     * Blanks the fallback of every `var(--name, fallback)` (balanced parentheses; may span lines).
     */
    private function withoutFallbacks(string $contents): string
    {
        $out = '';
        $length = \strlen($contents);
        $i = 0;
        while (false !== ($next = strpos($contents, 'var(', $i))) {
            $out .= substr($contents, $i, $next - $i);
            $i = $next;
            if (1 !== preg_match('/\Gvar\(\s*--[\w-]+\s*,/', $contents, $m, 0, $i)) {
                $out .= 'var(';
                $i += 4;

                continue;
            }
            $out .= $m[0];
            $i += \strlen($m[0]);
            $start = $i;
            $blank = '';
            for ($depth = 1; $i < $length; ++$i) {
                $char = $contents[$i];
                $depth += '(' === $char ? 1 : (')' === $char ? -1 : 0);
                if (0 === $depth) {
                    break;
                }
                $blank .= "\n" === $char ? "\n" : ' ';
            }
            // An unterminated var( (audit IS-I7) blanks nothing: the rest of the file is still linted.
            if ($i >= $length) {
                $i = $start;

                continue;
            }
            $out .= $blank;
        }

        return $out.substr($contents, $i);
    }
}
