<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Avatar;

use Nowo\UiKitBundle\Avatar\AvatarUrlPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * 2.26 avatar URL policy (contract §3.1): https:// without userinfo, or a same-origin path with a single leading "/".
 */
final class AvatarUrlPolicyTest extends TestCase
{
    /**
     * The shared case table (also read by nowo-ui-avatar.test.ts and copied by the bundles that keep their own policy).
     */
    public const CASES = __DIR__.'/../../Fixtures/avatar/url-policy-cases.json';

    /**
     * @return iterable<string, array{string}>
     */
    public static function accepted(): iterable
    {
        foreach (self::cases()['accepted'] as $name => $url) {
            yield $name => [$url];
        }
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function rejected(): iterable
    {
        yield 'null' => [null];
        // Not valid UTF-8 (cannot be written in the JSON table; ChatKit audit EE-L1): the bytes alone pass the
        // character class, so the policy must check the encoding.
        yield 'invalid UTF-8 byte' => ["/a\xFFb.png"];
        yield 'lone continuation byte' => ["/a\x80.png"];
        yield 'truncated sequence' => ["/\xC3"];
        yield 'overlong slash' => ["/\xC0\xAF.png"];
        yield 'UTF-16 surrogate' => ["/\xED\xA0\x80.png"];
        yield 'above U+10FFFF' => ["/\xF4\x90\x80\x80.png"];
        yield 'invalid UTF-8 in https host' => ["https://cdn\xFF.example.com/a.png"];
        foreach (self::cases()['rejected'] as $name => $url) {
            yield $name => [$url];
        }
    }

    public function testTheTableCoversTheAuditCases(): void
    {
        $rejected = self::cases()['rejected'];
        foreach (["/files/a/s64\n", "https://cdn.example.com/a\n", '/'.str_repeat('a', 2047)."\n", '/a"/onerror="alert(1)', 'https://[::1]/a.png', '/'.str_repeat('é', 1024)] as $url) {
            self::assertContains($url, $rejected);
        }
        self::assertSame(AvatarUrlPolicy::MAX_BYTES, \strlen(self::cases()['accepted']['non-ASCII path, 2048 bytes']));
        self::assertContains('https://cdn.example.com:0/a.png', $rejected);
    }

    public function testThePatternAloneRejectsMalformedUtf8(): void
    {
        // The `_avatar` partial applies PATTERN without sanitize(): the pattern must reject these on its own.
        foreach (["/a\xFFb.png", "/\xC3", "/\xC0\xAF", "/\xED\xA0\x80", "/\xF4\x90\x80\x80"] as $url) {
            self::assertSame(0, preg_match(AvatarUrlPolicy::PATTERN, $url), bin2hex($url));
        }
        foreach (["/\xC3\xA9", "/\xE2\x82\xAC", "/\xF0\x9F\x98\x80", "/\xED\x9F\xBF", "/\xF4\x8F\xBF\xBF"] as $url) {
            self::assertSame(1, preg_match(AvatarUrlPolicy::PATTERN, $url), bin2hex($url));
            self::assertSame($url, AvatarUrlPolicy::sanitize($url));
        }
    }

    public function testPortZeroAndLeadingZerosAreRejected(): void
    {
        foreach (['0', '00', '0443', '00443', '065535'] as $port) {
            self::assertNull(AvatarUrlPolicy::sanitize('https://cdn.example.com:'.$port.'/a.png'), $port);
        }
        foreach (['1', '9', '10', '443', '8443', '65535'] as $port) {
            self::assertNotNull(AvatarUrlPolicy::sanitize('https://cdn.example.com:'.$port.'/a.png'), $port);
        }
    }

    #[DataProvider('accepted')]
    public function testAccepted(string $url): void
    {
        self::assertSame($url, AvatarUrlPolicy::sanitize($url));
        self::assertTrue(AvatarUrlPolicy::isAllowed($url));
    }

    #[DataProvider('rejected')]
    public function testRejected(?string $url): void
    {
        self::assertNull(AvatarUrlPolicy::sanitize($url));
        self::assertFalse(AvatarUrlPolicy::isAllowed($url));
    }

    /**
     * @return array{accepted: array<string, string>, rejected: array<string, string>}
     */
    private static function cases(): array
    {
        /** @var array{accepted: array<string, string>, rejected: array<string, string>} $cases */
        $cases = json_decode((string) file_get_contents(self::CASES), true, 512, \JSON_THROW_ON_ERROR);

        return $cases;
    }
}
