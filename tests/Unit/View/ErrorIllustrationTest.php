<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\View;

use InvalidArgumentException;
use Nowo\UiKitBundle\View\ErrorIllustration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.47: ErrorIllustration::fromUrl() (provider illustrations) next to the 2.6 asset path mode.
 */
final class ErrorIllustrationTest extends TestCase
{
    public function testAssetPathModeIsUnchanged(): void
    {
        $illustration = new ErrorIllustration('illustrations/error-404', 896, 597, false, '.jpg');

        self::assertFalse($illustration->isUrl());
        self::assertSame('illustrations/error-404.webp', $illustration->webpSrc());
        self::assertSame('illustrations/error-404.jpg', $illustration->fallbackSrc());
        self::assertNull($illustration->src);
        self::assertNull($illustration->srcset);
    }

    public function testFromUrl(): void
    {
        $illustration = ErrorIllustration::fromUrl('https://cdn.example/404.png', 640, 480, '/404.webp 1x, /404@2x.webp 2x', '//cdn.example/404.png 640w,/404-big.png 1280w', '(min-width: 40em) 640px, 100vw');

        self::assertTrue($illustration->isUrl());
        self::assertSame('https://cdn.example/404.png', $illustration->src);
        self::assertSame([640, 480, true], [$illustration->width, $illustration->height, $illustration->webp]);
        self::assertSame('/404.webp 1x, /404@2x.webp 2x', $illustration->webpSrcset);
        self::assertSame('//cdn.example/404.png 640w,/404-big.png 1280w', $illustration->srcset);
        self::assertSame('(min-width: 40em) 640px, 100vw', $illustration->sizes);
        self::assertFalse(ErrorIllustration::fromUrl('errors/404.svg', 1, 1)->webp);
        self::assertSame('a,b.png 1x, c.png,', ErrorIllustration::fromUrl('/x.png', 1, 1, srcset: 'a,b.png 1x, c.png,')->srcset, 'commas inside a URL');
        self::assertSame('HTTP://x/a.png', ErrorIllustration::fromUrl('HTTP://x/a.png', 1, 1)->src);
    }

    /**
     * @return iterable<string, array{0: callable(): ErrorIllustration, 1: string}>
     */
    public static function invalid(): iterable
    {
        yield 'empty path' => [static fn (): ErrorIllustration => new ErrorIllustration(' '), 'non-empty asset path'];
        yield 'zero width' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl('/a.png', 0, 1), 'must be positive'];
        yield 'javascript scheme' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl('javascript:alert(1)', 1, 1), '"javascript" scheme'];
        yield 'data scheme' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl('data:image/png;base64,AAAA', 1, 1), '"data" scheme'];
        yield 'whitespace' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl('/a b.png', 1, 1), 'without whitespace'];
        yield 'control character' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl("/a\x01.png", 1, 1), 'without whitespace'];
        yield 'srcset scheme' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl('/a.png', 1, 1, srcset: '/a.png 1x, vbscript:x 2x'), '"vbscript" scheme'];
        yield 'srcset trailing scheme' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl('/a.png', 1, 1, srcset: 'javascript:x,'), '"javascript" scheme'];
        yield 'srcset descriptor' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl('/a.png', 1, 1, webpSrcset: '/a.webp 2y'), 'descriptor "2y"'];
        yield 'srcset descriptor injection' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl('/a.png', 1, 1, srcset: '/a.png 1x" onerror="x'), 'descriptor'];
        yield 'empty srcset' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl('/a.png', 1, 1, srcset: ' , '), 'empty srcset'];
        yield 'sizes' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl('/a.png', 1, 1, sizes: '100vw" x="'), 'invalid sizes'];
        yield 'blank sizes' => [static fn (): ErrorIllustration => ErrorIllustration::fromUrl('/a.png', 1, 1, sizes: ' '), 'invalid sizes'];
        yield 'srcset in asset mode' => [static fn (): ErrorIllustration => new ErrorIllustration('a', srcset: 'a.png 1x'), 'srcset needs the URL mode'];
        yield 'sizes in asset mode' => [static fn (): ErrorIllustration => new ErrorIllustration('a', sizes: '100vw'), 'sizes needs the URL mode'];
    }

    /**
     * @param callable(): ErrorIllustration $build
     */
    #[DataProvider('invalid')]
    public function testInvalid(callable $build, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        $build();
    }
}
