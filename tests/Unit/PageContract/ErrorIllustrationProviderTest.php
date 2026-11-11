<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Closure;
use Nowo\UiKitBundle\Twig\ErrorPageExtension;
use Nowo\UiKitBundle\View\ErrorIllustration;
use Nowo\UiKitBundle\View\ErrorIllustrationProviderInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\HttpFoundation\Request;
use TypeError;

/**
 * 2.47: optional error page illustration provider. Without a provider (or when it answers null, throws or builds an
 * invalid illustration) the page is byte-identical to UiKit 2.46.0: the fixtures under tests/Fixtures/bc-2.46 were
 * rendered by the 2.46.0 templates (commit 41ac2e4) from exactly these scenarios, no whitespace normalisation
 * (`UIKIT_BC_2_46_WRITE=1` rewrites them).
 */
final class ErrorIllustrationProviderTest extends TestCase
{
    private const HOST = "{% extends '@NowoUiKit/error/status.html.twig' %}";

    private const MAP = [
        'illustrations' => [
            404 => ['path' => 'illustrations/error-404', 'width' => 896, 'height' => 597],
            'default' => ['path' => 'illustrations/error', 'webp' => false, 'extension' => 'jpg'],
        ],
    ];

    /**
     * @return iterable<string, array{0: string, 1: int, 2: array<string, mixed>}>
     */
    public static function scenarios(): iterable
    {
        yield 'map_404' => ['map_404', 404, self::MAP];
        yield 'map_default_500' => ['map_default_500', 500, self::MAP];
        yield 'none_404' => ['none_404', 404, []];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('scenarios')]
    public function testWithoutProviderTheOutputIsByteIdentical(string $name, int $code, array $config): void
    {
        $html = $this->render($code, $config);
        $file = \dirname(__DIR__, 2).'/Fixtures/bc-2.46/error_'.$name.'.html';
        if ('1' === getenv('UIKIT_BC_2_46_WRITE')) {
            if (!is_dir(\dirname($file))) {
                mkdir(\dirname($file), 0o777, true);
            }
            file_put_contents($file, $html);
        }

        self::assertSame((string) file_get_contents($file), $html);
    }

    /**
     * @return iterable<string, array{0: Closure(int): ?ErrorIllustration, 1: bool}>
     */
    public static function fallbacks(): iterable
    {
        yield 'null' => [static fn (int $code): ?ErrorIllustration => null, false];
        yield 'exception' => [static fn (int $code): ?ErrorIllustration => throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused'), true];
        yield 'invalid illustration' => [static fn (int $code): ErrorIllustration => ErrorIllustration::fromUrl('javascript:alert(1)', 10, 10), true];
        yield 'invalid srcset' => [static fn (int $code): ErrorIllustration => ErrorIllustration::fromUrl('/a.png', 10, 10, srcset: '/a.png 2y'), true];
        yield 'error' => [static fn (int $code): ?ErrorIllustration => throw new TypeError('broken provider'), true];
    }

    /**
     * @param Closure(int): ?ErrorIllustration $answer
     */
    #[DataProvider('fallbacks')]
    public function testFallbackPathsRenderTheConfiguredMap(Closure $answer, bool $warns): void
    {
        foreach (self::scenarios() as [$name, $code, $config]) {
            $provider = new CallbackIllustrationProvider($answer);
            $logger = new RecordingLogger();
            $html = $this->render($code, $config, $provider, $logger);

            self::assertSame((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/bc-2.46/error_'.$name.'.html'), $html, $name);
            self::assertSame([$code], $provider->calls, $name);
            self::assertSame($warns ? ['warning'] : [], array_column($logger->records, 0), $name);
        }
    }

    public function testFallbackWithoutLoggerStillRenders(): void
    {
        $html = $this->render(404, self::MAP, new CallbackIllustrationProvider(static fn (int $code): ?ErrorIllustration => throw new RuntimeException('down')));

        self::assertSame((string) file_get_contents(\dirname(__DIR__, 2).'/Fixtures/bc-2.46/error_map_404.html'), $html);
    }

    public function testProvidedIllustrationRendersPictureWithEscapedUrls(): void
    {
        $provider = new CallbackIllustrationProvider(static fn (int $code): ErrorIllustration => ErrorIllustration::fromUrl(
            'https://cdn.example/brand/404.png?v=2&t="x"',
            640,
            480,
            webpSrcset: 'https://cdn.example/brand/404.webp?v=2&s=1 1x, https://cdn.example/brand/404@2x.webp 2x',
            srcset: 'https://cdn.example/brand/404.png?v=2&s=1 1x, https://cdn.example/brand/404@2x.png 2x',
            sizes: '(max-width: 600px) 100vw, 640px',
        ));
        $html = $this->render(404, self::MAP, $provider);

        self::assertMatchesRegularExpression(
            '#<figure class="nowo-ui-error-page__figure">\s*<picture>\s*'
            .'<source type="image/webp" srcset="https://cdn.example/brand/404.webp\?v=2&amp;s=1 1x, https://cdn.example/brand/404@2x.webp 2x" sizes="\(max-width: 600px\) 100vw, 640px">\s*'
            .'<img class="nowo-ui-error-page__img" src="https://cdn.example/brand/404.png\?v=2&amp;t=&quot;x&quot;" srcset="https://cdn.example/brand/404.png\?v=2&amp;s=1 1x, https://cdn.example/brand/404@2x.png 2x" sizes="\(max-width: 600px\) 100vw, 640px" width="640" height="480" alt="[^"]+" decoding="async" fetchpriority="high">\s*'
            .'</picture>\s*</figure>#',
            $html,
        );
        self::assertStringNotContainsString('/bundles/nowouikit/', $this->figure($html), 'a provided URL never goes through asset()');
        self::assertSame(0, preg_match('/\son[a-z]+=|\sstyle=/i', $this->figure($html)), 'CSP: no inline handler or style');
    }

    public function testProvidedIllustrationWithoutOptionalSourcesIsAPlainImage(): void
    {
        $provider = new CallbackIllustrationProvider(static fn (int $code): ErrorIllustration => ErrorIllustration::fromUrl('/media/errors/'.$code.'.svg', 300, 200));
        $figure = $this->figure($this->render(503, [], $provider));

        self::assertMatchesRegularExpression('#^<figure class="nowo-ui-error-page__figure">\s*<picture>\s*<img class="nowo-ui-error-page__img" src="/media/errors/503.svg" width="300" height="200" alt="[^"]+" decoding="async" fetchpriority="high">\s*</picture>\s*</figure>$#', $figure);
    }

    public function testBudgetSkipsTheProviderForTheRestOfTheRequestOnly(): void
    {
        $provider = new CallbackIllustrationProvider(static function (int $code): ErrorIllustration {
            usleep(20_000);

            return ErrorIllustration::fromUrl('/slow/'.$code.'.png', 10, 10);
        });
        $logger = new RecordingLogger();
        $request = Request::create('https://app.example/x', 'GET');
        $twig = PageContractTwig::create(['e' => self::HOST], request: $request, errorPages: self::MAP + ['illustration_provider_budget_ms' => 5], illustrationProvider: $provider, logger: $logger);

        self::assertStringContainsString('src="/slow/404.png"', $twig->render('e', ['status_code' => 404]), 'the slow answer is still used');
        self::assertTrue($request->attributes->get(ErrorPageExtension::PROVIDER_SKIP_ATTRIBUTE));
        self::assertStringContainsString('src="/bundles/nowouikit/illustrations/error-404.png"', $twig->render('e', ['status_code' => 404]), 'same request: map');
        self::assertCount(1, $provider->calls);
        self::assertSame(['warning'], array_column($logger->records, 0));
        self::assertSame(5, $logger->records[0][2]['budget_ms'] ?? null);

        $next = Request::create('https://app.example/y', 'GET');
        $html = PageContractTwig::create(['e' => self::HOST], request: $next, errorPages: self::MAP + ['illustration_provider_budget_ms' => 5], illustrationProvider: $provider)->render('e', ['status_code' => 404]);
        self::assertStringContainsString('src="/slow/404.png"', $html, 'a new request asks the provider again');
        self::assertCount(2, $provider->calls);
    }

    public function testNoBudgetNeverSkips(): void
    {
        $provider = new CallbackIllustrationProvider(static function (int $code): ErrorIllustration {
            usleep(2_000);

            return ErrorIllustration::fromUrl('/p.png', 10, 10);
        });
        $request = Request::create('https://app.example/x', 'GET');
        $twig = PageContractTwig::create(['e' => self::HOST], request: $request, errorPages: self::MAP, illustrationProvider: $provider);
        $twig->render('e', ['status_code' => 404]);
        $twig->render('e', ['status_code' => 404]);

        self::assertCount(2, $provider->calls);
        self::assertFalse($request->attributes->has(ErrorPageExtension::PROVIDER_SKIP_ATTRIBUTE));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function render(int $code, array $config, ?ErrorIllustrationProviderInterface $provider = null, ?RecordingLogger $logger = null): string
    {
        $request = Request::create('https://app.example/missing', 'GET');

        return PageContractTwig::create(['e' => self::HOST], request: $request, errorPages: $config, illustrationProvider: $provider, logger: $logger)
            ->render('e', ['status_code' => $code]);
    }

    private function figure(string $html): string
    {
        preg_match('#<figure.*?</figure>#s', $html, $m);

        return $m[0] ?? '';
    }
}

/**
 * @internal
 */
final class CallbackIllustrationProvider implements ErrorIllustrationProviderInterface
{
    /** @var list<int> */
    public array $calls = [];

    /**
     * @param Closure(int): ?ErrorIllustration $answer
     */
    public function __construct(private readonly Closure $answer)
    {
    }

    public function getIllustration(int $statusCode): ?ErrorIllustration
    {
        $this->calls[] = $statusCode;

        return ($this->answer)($statusCode);
    }
}

/**
 * @internal
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{0: mixed, 1: string, 2: array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
}
