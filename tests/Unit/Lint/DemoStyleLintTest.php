<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Lint;

use InvalidArgumentException;
use Nowo\UiKitBundle\Lint\DemoStyleLint;
use PHPUnit\Framework\TestCase;

/**
 * 2.60 demo style lint: a demo has no styles of its own (stylesheet files, <style>, style="", foreign stylesheet links).
 */
final class DemoStyleLintTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/nowo-demo-style-lint-'.bin2hex(random_bytes(4));
        foreach (['templates/demo', 'public/css', 'public/bundles/nowophoneinput/css', 'assets', 'vendor/acme/x', 'node_modules/y', 'e2e'] as $d) {
            mkdir($this->dir.'/'.$d, 0o777, true);
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->dir));
    }

    public function testTemplateRules(): void
    {
        $twig = <<<'TWIG'
{# <style> in a comment is fine, style="x" too #}
<link rel="stylesheet" href="{{ asset('css/nowo-ui.css', 'nowo_ui_kit') }}">
<link rel="stylesheet" href="{{ asset('bundles/nowophoneinput/css/phone_input.css') }}">
<link rel="stylesheet" href="{{ asset('css/demo.css') }}">
<style>.x { color: red }</style>
<div style="margin-top: 1rem">x</div>
<div style="display:none">x</div> {# nowo-ui-demo-style-lint-ignore: shown by the widget script #}
<p class="nowo-ui-muted">ok</p>
{{ nowo_ui_tokens(nonce) }}
TWIG;
        $findings = (new DemoStyleLint())->lintTemplate($twig);

        self::assertSame([
            ['line' => 4, 'rule' => 'stylesheet-link', 'match' => '<link rel="stylesheet" href="{{ asset(\'css/demo.css\') }}">'],
            ['line' => 5, 'rule' => 'style-element', 'match' => '<style'],
            ['line' => 6, 'rule' => 'style-attribute', 'match' => 'style="'],
            ['line' => 9, 'rule' => 'host-tokens', 'match' => 'nowo_ui_tokens('],
        ], $findings);
    }

    public function testDemoDirectoryOwnStylesheetsAndSkippedDirectories(): void
    {
        file_put_contents($this->dir.'/templates/base.html.twig', "<html>\n<body style=\"padding:0\"></body>\n</html>\n");
        file_put_contents($this->dir.'/templates/demo/home.html.twig', "<p class=\"nowo-ui-muted\">ok</p>\n");
        file_put_contents($this->dir.'/public/css/demo.css', '.x{}');
        file_put_contents($this->dir.'/assets/app.css', '/* none */');
        file_put_contents($this->dir.'/public/bundles/nowophoneinput/css/phone_input.css', '.y{}');
        file_put_contents($this->dir.'/vendor/acme/x/a.css', '.z{}');
        file_put_contents($this->dir.'/node_modules/y/b.scss', '.z{}');
        file_put_contents($this->dir.'/e2e/report.css', '.z{}');

        $results = (new DemoStyleLint())->lintDemo($this->dir);
        self::assertSame(['assets/app.css', 'public/css/demo.css', 'templates/base.html.twig'], array_keys($results));
        self::assertSame('own-stylesheet', $results['public/css/demo.css'][0]['rule']);
        self::assertSame(['line' => 2, 'rule' => 'style-attribute', 'match' => 'style="'], $results['templates/base.html.twig'][0]);

        // A documented exemption is allow-listed by glob.
        $allowed = (new DemoStyleLint(['assets/*.css', 'public/css/*']))->lintDemo($this->dir);
        self::assertSame(['templates/base.html.twig'], array_keys($allowed));
    }

    public function testMissingDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new DemoStyleLint())->lintDemo($this->dir.'/nope');
    }
}
