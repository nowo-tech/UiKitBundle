<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Lint;

use InvalidArgumentException;
use Nowo\UiKitBundle\Lint\TokenLint;
use PHPUnit\Framework\TestCase;

/**
 * 2.40 (spec 220 §2.1): bin/nowo-ui-token-lint and its rules.
 */
final class TokenLintTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/nowo-ui-token-lint-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/css', 0o777, true);
        file_put_contents($this->dir.'/css/bundle.css', ".a { color: #fff; }\n.b { color: var(--nowo-ui-primary, #2563eb); }\n");
        file_put_contents($this->dir.'/css/nowo-ui.css', ".a { color: #123456; }\n");
        file_put_contents($this->dir.'/page.html.twig', "{# #abc in a comment #}\n<div style=\"color: rgba(0, 0, 0, .5)\">{{ x }}</div>\n");
        file_put_contents($this->dir.'/readme.md', "#fff\n");
    }

    protected function tearDown(): void
    {
        foreach (['css/bundle.css', 'css/nowo-ui.css', 'page.html.twig', 'readme.md'] as $file) {
            @unlink($this->dir.'/'.$file);
        }
        @rmdir($this->dir.'/css');
        @rmdir($this->dir);
    }

    public function testRules(): void
    {
        $lint = new TokenLint();
        $css = <<<'CSS'
            .a { color: #fff; background: #ffffff80; border-color: #abcd; }
            .b { color: var(--nowo-ui-primary, #2563eb); box-shadow: 0 1px 2px rgb(var(--nowo-ui-shadow-rgb) / 0.1); }
            .c { color: var(--nowo-ui-x, color-mix(in srgb, #000 10%, rgb(1 2 3))); }
            .d { color: var(--nowo-ui-y,
              #111); }
            /* #222 rgb(1, 2, 3) */
            .e { color: rgb(1 2 3); background: rgba(0,0,0,.1); }
            .f { color: #333; } /* nowo-ui-token-lint-ignore */
            .g { width: var(--w); color: #4444; content: "&#x27;"; }
            #main-content, .h#abc-def { color: inherit; }
            .i { color: var(--nowo-ui-open, #555
            CSS;

        self::assertSame([
            ['line' => 1, 'match' => '#fff'],
            ['line' => 1, 'match' => '#ffffff80'],
            ['line' => 1, 'match' => '#abcd'],
            ['line' => 7, 'match' => 'rgb('],
            ['line' => 7, 'match' => 'rgba('],
            ['line' => 9, 'match' => '#4444'],
            ['line' => 11, 'match' => '#555'],
        ], $lint->lintContents($css), 'an unterminated var( hides nothing');
    }

    public function testPathsAndAllowList(): void
    {
        $lint = new TokenLint();
        $results = $lint->lintPaths([$this->dir, $this->dir.'/readme.md']);
        self::assertSame([
            $this->dir.'/css/bundle.css' => [['line' => 1, 'match' => '#fff']],
            $this->dir.'/page.html.twig' => [['line' => 2, 'match' => 'rgba(']],
        ], $results, 'nowo-ui.css is allow-listed by default; .md is not linted');

        self::assertSame([$this->dir.'/css/bundle.css'], array_keys((new TokenLint(['*.twig']))->lintPaths([$this->dir.'/css/bundle.css', $this->dir.'/page.html.twig'])));
        self::assertArrayHasKey($this->dir.'/css/nowo-ui.css', (new TokenLint([]))->lintPaths([$this->dir]));
        self::assertTrue($lint->isAllowed('nowo-ui.css'));
        try {
            $lint->lintPaths([$this->dir, $this->dir.'/missing']);
            self::fail('a missing path must not pass as clean');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('/missing" does not exist', $e->getMessage());
        }
        self::assertFalse($lint->isAllowed('src/my.css'));
    }

    public function testBinScript(): void
    {
        $bin = \dirname(__DIR__, 3).'/bin/nowo-ui-token-lint';
        $run = static function (string $args) use ($bin): array {
            exec(\PHP_BINARY.' '.escapeshellarg($bin).' '.$args.' 2>&1', $output, $code);

            return [$code, implode("\n", $output)];
        };

        [$code, $out] = $run(escapeshellarg($this->dir));
        self::assertSame(1, $code);
        self::assertStringContainsString($this->dir.'/css/bundle.css:1: #fff', $out);
        self::assertStringContainsString('nowo-ui-token-lint: 2 finding(s).', $out);

        [$code] = $run('--allow='.escapeshellarg('*bundle.css').' --allow='.escapeshellarg('*.twig').' '.escapeshellarg($this->dir));
        self::assertSame(0, $code);

        [$code, $out] = $run('--no-default-allow --allow='.escapeshellarg('*.twig').' '.escapeshellarg($this->dir));
        self::assertSame(1, $code);
        self::assertStringContainsString('nowo-ui.css:1: #123456', $out);

        self::assertSame(0, $run('--help')[0]);
        self::assertSame(2, $run('--nope')[0]);
        [$code, $out] = $run(escapeshellarg($this->dir.'/typo'));
        self::assertSame(2, $code);
        self::assertStringContainsString('does not exist', $out);
    }
}
