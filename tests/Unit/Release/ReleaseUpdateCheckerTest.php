<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Release;

use Nowo\UiKitBundle\Release\ReleaseUpdateChecker;
use Nowo\UiKitBundle\Release\ReleaseUpdateInfo;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ReleaseUpdateCheckerTest extends TestCase
{
    private const REPO = 'acme/app';

    public function testSkipsWhenDisabled(): void
    {
        $checker = $this->checker($this->noHttp(), 'v1.0.6', enabled: false);

        $info = $checker->check();

        self::assertFalse($checker->isEnabled());
        self::assertSame(ReleaseUpdateInfo::STATUS_SKIPPED, $info->status);
        self::assertSame('v1.0.6', $info->current);
        self::assertFalse($info->updateAvailable);
    }

    public function testSkipsWhenVersionEmptyOrNotCleanSemver(): void
    {
        foreach (['', '   ', 'v1.0.6-3-gabcd123', 'abc1234', '1.0'] as $version) {
            $info = $this->checker($this->noHttp(), $version)->check();
            self::assertSame(ReleaseUpdateInfo::STATUS_SKIPPED, $info->status, $version);
        }
    }

    public function testSkipsInvalidRepo(): void
    {
        $logger = $this->logger();
        foreach (['', 'https://evil.example/x', 'a/b/c', '../x', 'a/..'] as $repo) {
            $info = $this->checker($this->noHttp(), 'v1.0.6', repo: $repo, logger: $logger)->check();
            self::assertSame(ReleaseUpdateInfo::STATUS_SKIPPED, $info->status, $repo);
        }
        self::assertNotEmpty($logger->records);
    }

    public function testDetectsUpdateAvailableAndCountsVersionsBehind(): void
    {
        $requests = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests): ResponseInterface {
            $requests[] = [$url, $options];
            if (str_contains($url, '/releases/latest')) {
                return $this->latestResponse('v1.2.0');
            }

            return $this->releasesListResponse([
                ['tag_name' => 'v1.2.0', 'draft' => false, 'prerelease' => false],
                ['tag_name' => 'v1.1.0', 'draft' => false, 'prerelease' => false],
                ['tag_name' => 'v1.1.5', 'draft' => true, 'prerelease' => false],
                ['tag_name' => 'v1.0.6', 'draft' => false, 'prerelease' => false],
                ['tag_name' => 'v1.3.0-rc.1', 'draft' => false, 'prerelease' => true],
                ['tag_name' => 'garbage'],
                ['draft' => false],
            ]);
        });

        $info = $this->checker($http, '1.0.6', userAgent: 'my-app')->check();

        self::assertSame(ReleaseUpdateInfo::STATUS_OK, $info->status);
        self::assertTrue($info->updateAvailable);
        self::assertSame('v1.2.0', $info->latest);
        self::assertSame(2, $info->versionsBehind);
        self::assertSame('https://github.com/acme/app/releases/tag/v1.2.0', $info->releaseUrl);
        self::assertSame('https://github.com/acme/app/compare/v1.0.6...v1.2.0', $info->compareUrl);
        self::assertNotNull($info->checkedAt);
        self::assertCount(2, $requests);
        self::assertSame('https://api.github.com/repos/acme/app/releases/latest', $requests[0][0]);
        self::assertContains('User-Agent: my-app', $requests[0][1]['headers']);
    }

    public function testUpToDateComparesAgainstDefaultBranch(): void
    {
        $info = $this->checker(new MockHttpClient([$this->latestResponse('v1.0.6')]), 'v1.0.6', defaultBranch: 'develop')->check();

        self::assertSame(ReleaseUpdateInfo::STATUS_OK, $info->status);
        self::assertFalse($info->updateAvailable);
        self::assertSame(0, $info->versionsBehind);
        self::assertSame('https://github.com/acme/app/compare/v1.0.6...develop', $info->compareUrl);
    }

    public function testEmptyDefaultBranchAndBlankUserAgentFallBack(): void
    {
        $seen = [];
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$seen): ResponseInterface {
            $seen = $options['headers'];

            return $this->latestResponse('v1.0.6');
        });
        $info = $this->checker($http, 'v1.0.6', userAgent: ' ', defaultBranch: ' ')->check();

        self::assertSame('https://github.com/acme/app/compare/v1.0.6...HEAD', $info->compareUrl);
        self::assertContains('User-Agent: nowo-ui-kit-release-check', $seen);
    }

    public function testInstalledNewerThanLatestHasUnknownBehind(): void
    {
        $info = $this->checker(new MockHttpClient([$this->latestResponse('v1.0.0')]), 'v2.0.0')->check();

        self::assertSame(ReleaseUpdateInfo::STATUS_OK, $info->status);
        self::assertFalse($info->updateAvailable);
        self::assertNull($info->versionsBehind);
    }

    public function testHttpErrorSoftFails(): void
    {
        $logger = $this->logger();
        $info = $this->checker(new MockHttpClient([new MockResponse('boom', ['http_code' => 503])]), 'v1.0.6', logger: $logger)->check();

        self::assertSame(ReleaseUpdateInfo::STATUS_ERROR, $info->status);
        self::assertFalse($info->updateAvailable);
        self::assertNull($info->latest);
        self::assertNotEmpty($logger->records);
    }

    public function testTransportExceptionSoftFails(): void
    {
        $http = new MockHttpClient(static function (): never {
            throw new \RuntimeException('network down');
        });

        self::assertSame(ReleaseUpdateInfo::STATUS_ERROR, $this->checker($http, 'v1.0.6')->check()->status);
    }

    public function testLatestNonSemverTagIsError(): void
    {
        $info = $this->checker(new MockHttpClient([$this->latestResponse('nightly')]), 'v1.0.6')->check();

        self::assertSame(ReleaseUpdateInfo::STATUS_ERROR, $info->status);
        self::assertSame('nightly', $info->latest);
        self::assertNotNull($info->releaseUrl);
    }

    public function testMalformedOrNonGithubPayloadSoftFails(): void
    {
        foreach ([
            ['tag_name' => '', 'html_url' => 'https://github.com/acme/app/releases/tag/v1.0.0'],
            ['tag_name' => 'v1.1.0', 'html_url' => 'https://evil.example/releases/tag/v1.1.0'],
            ['tag_name' => 1, 'html_url' => null],
        ] as $payload) {
            $http = new MockHttpClient([new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR), ['http_code' => 200])]);
            self::assertSame(ReleaseUpdateInfo::STATUS_ERROR, $this->checker($http, 'v1.0.6')->check()->status);
        }
    }

    public function testVersionsBehindFailuresLeaveBehindNull(): void
    {
        $info = $this->checker(new MockHttpClient([
            $this->latestResponse('v1.1.0'),
            new MockResponse('nope', ['http_code' => 500]),
        ]), 'v1.0.6')->check();
        self::assertTrue($info->updateAvailable);
        self::assertNull($info->versionsBehind);

        $http = new MockHttpClient(function (string $method, string $url): ResponseInterface {
            if (str_contains($url, '/releases/latest')) {
                return $this->latestResponse('v1.1.0');
            }
            throw new \RuntimeException('list down');
        });
        $info = $this->checker($http, 'v1.0.6')->check();
        self::assertTrue($info->updateAvailable);
        self::assertNull($info->versionsBehind);
    }

    public function testCacheHitAvoidsSecondRoundTrip(): void
    {
        $calls = 0;
        $http = new MockHttpClient(function (string $method, string $url) use (&$calls): ResponseInterface {
            ++$calls;
            if (str_contains($url, '/releases/latest')) {
                return $this->latestResponse('v1.1.0');
            }

            return $this->releasesListResponse([['tag_name' => 'v1.1.0', 'draft' => false, 'prerelease' => false]]);
        });
        $checker = $this->checker($http, 'v1.0.6', cache: new ArrayAdapter());

        $first = $checker->check();
        $second = $checker->check();

        self::assertSame(2, $calls);
        self::assertSame(1, $first->versionsBehind);
        self::assertSame('v1.1.0', $second->latest);
        self::assertSame('v1.0.6', $checker->getCurrentVersion());
    }

    public function testInvalidCachedCheckedAtIsNull(): void
    {
        $cache = new ArrayAdapter();
        $checker = $this->checker($this->noHttp(), 'v1.0.6', cache: $cache);
        // Prime the cache with a payload carrying an unparsable timestamp.
        $primer = $this->checker(new MockHttpClient([$this->latestResponse('v1.0.6')]), 'v1.0.6', cache: $cache);
        $primer->check();
        $item = $cache->getItem(array_keys($cache->getValues())[0]);
        /** @var array<string, mixed> $value */
        $value = $item->get();
        $value['at'] = 'not a date';
        $cache->save($item->set($value));

        self::assertNull($checker->check()->checkedAt);
    }

    public function testStaticHelpers(): void
    {
        self::assertSame('1.2.3', ReleaseUpdateChecker::parseCleanSemver(' V1.2.3 '));
        self::assertNull(ReleaseUpdateChecker::parseCleanSemver('1.2.3-beta'));
        self::assertTrue(ReleaseUpdateChecker::isValidRepo('nowo-tech/Ui_Kit.Bundle'));
        self::assertFalse(ReleaseUpdateChecker::isValidRepo('nowo-tech'));
    }

    public function testInfoToArray(): void
    {
        $info = new ReleaseUpdateInfo(ReleaseUpdateInfo::STATUS_OK, 'v1', 'v2', true, 1, 'r', 'c', new \DateTimeImmutable('2026-10-09T08:00:00+00:00'));

        self::assertSame([
            'status' => 'ok',
            'current' => 'v1',
            'latest' => 'v2',
            'updateAvailable' => true,
            'versionsBehind' => 1,
            'releaseUrl' => 'r',
            'compareUrl' => 'c',
            'checkedAt' => '2026-10-09T08:00:00+00:00',
        ], $info->toArray());
        self::assertNull((new ReleaseUpdateInfo('skipped', ''))->toArray()['checkedAt']);
    }

    private function checker(
        HttpClientInterface $http,
        string $version,
        bool $enabled = true,
        string $repo = self::REPO,
        ?CacheInterface $cache = null,
        string $userAgent = 'nowo-ui-kit-release-check',
        string $defaultBranch = 'main',
        ?AbstractLogger $logger = null,
    ): ReleaseUpdateChecker {
        return new ReleaseUpdateChecker($http, $cache ?? new ArrayAdapter(), $version, $repo, $enabled, 3600, $userAgent, $defaultBranch, $logger);
    }

    private function noHttp(): MockHttpClient
    {
        return new MockHttpClient(static function (): never {
            self::fail('HTTP must not be called');
        });
    }

    private function latestResponse(string $tag): MockResponse
    {
        return new MockResponse(json_encode([
            'tag_name' => $tag,
            'html_url' => 'https://github.com/acme/app/releases/tag/'.$tag,
        ], \JSON_THROW_ON_ERROR), ['http_code' => 200]);
    }

    /**
     * @param list<array<string, mixed>> $releases
     */
    private function releasesListResponse(array $releases): MockResponse
    {
        return new MockResponse(json_encode($releases, \JSON_THROW_ON_ERROR), ['http_code' => 200]);
    }

    /**
     * @return AbstractLogger&object{records: list<string>}
     */
    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger {
            /** @var list<string> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = (string) $message;
            }
        };
    }
}
