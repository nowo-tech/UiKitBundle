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
