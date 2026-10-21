<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Twig;

use Nowo\UiKitBundle\Twig\ErrorPageExtension;
use Nowo\UiKitBundle\View\ErrorIllustration;
use Nowo\UiKitBundle\View\ErrorPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

/**
 * nowo_ui_error_page() / nowo_ui_error_text() (2.6): REQ-ERROR-001 item 10 decisions and the host → UiKit text
 * fallback.
 */
final class ErrorPageExtensionTest extends TestCase
{
    public function testFunctionsGlobalsAndDefaults(): void
    {
        $ext = new ErrorPageExtension(['theme_storage' => 'beacon-theme', 'html_attributes' => ['data-csp-strict-nonce' => true]]);

        self::assertSame(['nowo_ui_error_page', 'nowo_ui_error_text'], array_map(static fn ($f): string => $f->getName(), $ext->getFunctions()));
        self::assertSame([
            'layout_template' => '@NowoUiKit/error/layout.html.twig',
            'theme_storage' => 'beacon-theme',
            'theme_toggle' => true,
            'html_attributes' => ['data-csp-strict-nonce' => true],
            'translation_domain' => 'NowoUiKitBundle',
            'translation_prefix' => 'error',
        ], $ext->getGlobals()['nowo_ui_error_pages']);
        self::assertSame(600, ErrorPageExtension::defaults()['auto_retry_seconds']);
    }

    /**
     * @return iterable<string, array{int, string, string|null, string|null, int}>
     */
    public static function retryCases(): iterable
    {
        yield '500 GET: retry + 10 min reload' => [500, 'GET', '/orders?page=2', null, 600];
        yield '503 GET' => [503, 'GET', '/orders?page=2', null, 600];
        yield '500 POST: neither (no resubmission)' => [500, 'POST', null, null, 0];
        yield '408 GET: retry, no reload' => [408, 'GET', '/orders?page=2', null, 0];
        yield '404 GET: back from a same-site referer' => [404, 'GET', null, 'https://app.example/orders', 0];
        yield '403 GET: nothing' => [403, 'GET', null, null, 0];
    }

    #[DataProvider('retryCases')]
    public function testRetryBackAndReload(int $code, string $method, ?string $retry, ?string $back, int $reload): void
    {
        $request = Request::create('https://app.example/orders?page=2', $method, server: ['HTTP_REFERER' => 'https://app.example/orders']);
        $page = (new ErrorPageExtension([], null, $this->stack($request)))->page($code);

        self::assertSame($code, $page->statusCode);
        self::assertSame($retry, $page->retryHref);
        self::assertSame($back, $page->backHref);
        self::assertSame($reload, $page->autoRetrySeconds);
        self::assertSame($reload > 0, $page->autoRetries());
        self::assertSame('/', $page->homeHref);
        self::assertNull($page->illustration);
        self::assertNull($page->retryAfter);
    }

    public function testForeignRefererNoRequestAndConfiguredStatuses(): void
    {
        $foreign = Request::create('https://app.example/x', 'GET', server: ['HTTP_REFERER' => 'https://evil.example/']);
        self::assertNull((new ErrorPageExtension([], null, $this->stack($foreign)))->page(404)->backHref);
        $prefix = Request::create('https://app.example/x', 'GET', server: ['HTTP_REFERER' => 'https://app.example.evil.test/']);
        self::assertNull((new ErrorPageExtension([], null, $this->stack($prefix)))->page(404)->backHref);

        $none = (new ErrorPageExtension())->page('500');
        self::assertSame([500, null, null, 0], [$none->statusCode, $none->retryHref, $none->backHref, $none->autoRetrySeconds]);
        self::assertSame(0, (new ErrorPageExtension())->page(null)->statusCode);

        $request = Request::create('https://app.example/x', 'GET', server: ['HTTP_REFERER' => 'https://app.example/list']);
        $ext = new ErrorPageExtension(['retry_statuses' => [429], 'back_link_statuses' => [403], 'auto_retry_seconds' => 0, 'home_path' => '/app'], null, $this->stack($request));
        self::assertSame('/x', $ext->page(429)->retryHref);
        self::assertNull($ext->page(408)->retryHref);
        self::assertSame('https://app.example/list', $ext->page(403)->backHref);
        self::assertSame(0, $ext->page(500)->autoRetrySeconds);
        self::assertFalse($ext->page(500)->autoRetries());
        self::assertSame('/app', $ext->page(500)->homeHref);
    }

    public function testRetryAfterFromTheExceptionHeaders(): void
    {
        $ext = new ErrorPageExtension();
        self::assertSame(30, $ext->page(429, FlattenException::createFromThrowable(new TooManyRequestsHttpException(30)))->retryAfter);
        self::assertSame(12, $ext->page(429, ['retry-after' => ['12']])->retryAfter);
        self::assertNull($ext->page(429, ['Retry-After' => 'Wed, 21 Oct 2026 07:28:00 GMT'])->retryAfter);
        self::assertNull($ext->page(429, ['X-Other' => '5'])->retryAfter);
        self::assertNull($ext->page(429, 'nope')->retryAfter);
        self::assertNull($ext->page(429)->retryAfter);
        self::assertNull($ext->page(503, ['Retry-After' => '30'])->retryAfter, 'only 429 shows the wait');
    }

    public function testIllustrations(): void
    {
        $ext = new ErrorPageExtension([
            'illustrations' => [
                404 => ['path' => 'illustrations/error-404'],
                '503' => ['path' => 'img/maintenance', 'width' => 1536, 'height' => 1024, 'webp' => false, 'extension' => 'jpg'],
                'default' => ['path' => 'illustrations/error'],
            ],
            'illustration_width' => 896,
            'illustration_height' => 597,
        ]);

        $notFound = $ext->page(404)->illustration;
        self::assertEquals(new ErrorIllustration('illustrations/error-404', 896, 597, true, 'png'), $notFound);
        self::assertSame(['illustrations/error-404.webp', 'illustrations/error-404.png'], [$notFound?->webpSrc(), $notFound?->fallbackSrc()]);
        self::assertEquals(new ErrorIllustration('img/maintenance', 1536, 1024, false, 'jpg'), $ext->page(503)->illustration);
        self::assertSame('illustrations/error', $ext->page(418)->illustration?->path);
    }

    public function testTextsFallBackFromTheHostDomainToUiKit(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', [
            'error.404.title' => 'Off the map',
            'error.404.hint' => 'Even frogs take a wrong turn.',
            'error.generic.title' => 'Unexpected detour',
            'error.home' => 'Hop home',
            'error.retry_after' => 'Wait %seconds%s',
        ], 'en', 'messages');
        $translator->addResource('array', [
            'error.404.title' => 'Page not found',
            'error.404.lead' => 'Check the address.',
            'error.410.title' => 'Page removed',
            'error.generic.title' => 'Something went wrong',
            'error.generic.lead' => 'Try again.',
            'error.generic.image_alt' => 'Illustration of the %code% error',
            'error.back' => 'Go back',
        ], 'en', 'NowoUiKitBundle');

        $host = new ErrorPageExtension(['translation_domain' => 'messages', 'translation_prefix' => 'error'], $translator);
        self::assertSame('Off the map', $host->text('title', 404));
        self::assertSame('Check the address.', $host->text('lead', 404), 'kit text when the host lacks it');
        self::assertSame('Even frogs take a wrong turn.', $host->text('hint', 404));
        self::assertSame('Illustration of the 404 error', $host->text('image_alt', 404), 'generic alt with %code%');
        self::assertSame('', $host->text('hint', 410), 'a known code never borrows the generic hint');
        self::assertSame('Page removed', $host->text('title', 410));
        self::assertSame('Unexpected detour', $host->text('title', 418), 'unknown code: host generic first');
        self::assertSame('Try again.', $host->text('lead', 418));
        self::assertSame('Unexpected detour', $host->text('title'));
        self::assertSame('Hop home', $host->text('home'));
        self::assertSame('Go back', $host->text('back'));
        self::assertSame('Wait 9s', $host->text('retry_after', 429, ['%seconds%' => 9]));
        self::assertSame('missing_field', $host->text('missing_field'));
        self::assertSame('', $host->text('hint', 418));

        $kit = new ErrorPageExtension([], $translator);
        self::assertSame('Page not found', $kit->text('title', 404));

        $prefixless = new ErrorPageExtension(['translation_domain' => 'messages', 'translation_prefix' => ''], $translator);
        self::assertSame('Page not found', $prefixless->text('title', 404), 'no "404.title" in messages: UiKit');

        $none = new ErrorPageExtension();
        self::assertSame('home', $none->text('home'));
        self::assertSame('', $none->text('title', 404));
    }

    public function testViewModels(): void
    {
        self::assertSame('10:00', (new ErrorPage(500, retryHref: '/', autoRetrySeconds: 600))->autoRetryClock());
        self::assertSame('1:05', (new ErrorPage(500, autoRetrySeconds: 65))->autoRetryClock());
        self::assertFalse((new ErrorPage(500, autoRetrySeconds: 65))->autoRetries(), 'no URL, no reload');
        self::assertSame('a.avif', (new ErrorIllustration('a', fallbackExtension: '.avif'))->fallbackSrc());
    }

    public function testIllustrationValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ErrorIllustration(' ');
    }

    public function testIllustrationSizeValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('width and height must be positive');
        new ErrorIllustration('a', 0, 10);
    }

    private function stack(Request $request): RequestStack
    {
        $stack = new RequestStack();
        $stack->push($request);

        return $stack;
    }
}
