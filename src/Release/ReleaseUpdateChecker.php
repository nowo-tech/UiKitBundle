<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Release;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Compares the installed version to GitHub `releases/latest` of one public repository.
 *
 * Opt-in (`nowo_ui_kit.release_check.enabled`). Every failure soft-fails to
 * {@see ReleaseUpdateInfo::STATUS_ERROR} / {@see ReleaseUpdateInfo::STATUS_SKIPPED};
 * results are cached so the GitHub API is hit at most once per TTL and version.
 * Intended for on-demand use (dialog / JSON endpoint), not every HTML render.
 *
 * Stateless (readonly) — safe for FrameworkBundle worker runtimes (REQ-CS-008).
 */
final readonly class ReleaseUpdateChecker
{
    public const DEFAULT_CACHE_TTL = 43_200; // 12h

    private const API_HOST = 'https://api.github.com';

    private const RELEASES_PAGE_SIZE = 100;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private string $currentVersion,
        private string $githubRepo,
        private bool $enabled = true,
        private int $cacheTtl = self::DEFAULT_CACHE_TTL,
        private string $userAgent = 'nowo-ui-kit-release-check',
        private string $defaultBranch = 'main',
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getCurrentVersion(): string
    {
        return trim($this->currentVersion);
    }

    public function check(): ReleaseUpdateInfo
    {
        $currentRaw = trim($this->currentVersion);
        $currentNorm = self::parseCleanSemver($currentRaw);

        if (!$this->enabled || null === $currentNorm) {
            return new ReleaseUpdateInfo(status: ReleaseUpdateInfo::STATUS_SKIPPED, current: $currentRaw);
        }

        $repo = trim($this->githubRepo);
        if (!self::isValidRepo($repo)) {
            $this->logger?->warning('Release update check skipped: invalid GitHub repository.', ['repo' => $repo]);

            return new ReleaseUpdateInfo(status: ReleaseUpdateInfo::STATUS_SKIPPED, current: $currentRaw);
        }

        try {
            /** @var array{tag: string, url: string, at: string, behind: int|null} $payload */
            $payload = $this->cache->get(
                $this->cacheKey($repo, $currentNorm),
                function (ItemInterface $item) use ($repo, $currentNorm): array {
                    $item->expiresAfter(max(60, $this->cacheTtl));
                    $fetched = $this->fetchLatestRelease($repo);
                    if (null === $fetched) {
                        throw new \RuntimeException('GitHub latest release fetch failed.');
                    }

                    $latestNorm = self::parseCleanSemver($fetched['tag']);
                    $behind = null;
                    if (null !== $latestNorm && version_compare($latestNorm, $currentNorm, '>')) {
                        $behind = $this->countVersionsBehind($repo, $currentNorm);
                    } elseif (null !== $latestNorm && version_compare($latestNorm, $currentNorm, '==')) {
                        $behind = 0;
                    }

                    $fetched['behind'] = $behind;

                    return $fetched;
                },
            );
        } catch (\Throwable $e) {
            $this->logger?->warning('Release update check cache/fetch failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);

            return new ReleaseUpdateInfo(status: ReleaseUpdateInfo::STATUS_ERROR, current: $currentRaw);
        }

        $checkedAt = $this->parseCheckedAt($payload['at']);
        $latestNorm = self::parseCleanSemver($payload['tag']);
        if (null === $latestNorm) {
            return new ReleaseUpdateInfo(
                status: ReleaseUpdateInfo::STATUS_ERROR,
                current: $currentRaw,
                latest: $payload['tag'],
                releaseUrl: $payload['url'],
                checkedAt: $checkedAt,
            );
        }

        $updateAvailable = version_compare($latestNorm, $currentNorm, '>');

        return new ReleaseUpdateInfo(
            status: ReleaseUpdateInfo::STATUS_OK,
            current: $currentRaw,
            latest: $payload['tag'],
            updateAvailable: $updateAvailable,
            versionsBehind: $payload['behind'],
            releaseUrl: $payload['url'],
            compareUrl: $this->githubCompareUrl($repo, $currentRaw, $updateAvailable ? $payload['tag'] : ('' !== trim($this->defaultBranch) ? trim($this->defaultBranch) : 'HEAD')),
            checkedAt: $checkedAt,
        );
    }

    /**
     * Clean `x.y.z` only (optional leading `v`). Rejects git-describe suffixes, pre-releases and SHAs.
     */
    public static function parseCleanSemver(string $raw): ?string
    {
        if (1 !== preg_match('/^v?(\d+\.\d+\.\d+)$/i', trim($raw), $m)) {
            return null;
        }

        return $m[1];
    }

    /**
     * `owner/name` with GitHub-safe characters only (no path traversal, no host override).
     */
    public static function isValidRepo(string $repo): bool
    {
        return 1 === preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repo)
            && !str_contains($repo, '..');
    }

    /**
     * @return array{tag: string, url: string, at: string}|null
     */
    private function fetchLatestRelease(string $repo): ?array
    {
        try {
            $response = $this->httpClient->request('GET', self::API_HOST.'/repos/'.$repo.'/releases/latest', [
                'headers' => $this->githubHeaders(),
                'timeout' => 5,
                'max_redirects' => 0,
            ]);
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                $this->logger?->warning('Release update check HTTP non-success.', ['status' => $status, 'repo' => $repo]);

                return null;
            }

            /** @var array{tag_name?: mixed, html_url?: mixed} $data */
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger?->warning('Release update check HTTP failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'repo' => $repo,
            ]);

            return null;
        }

        $tag = isset($data['tag_name']) && \is_string($data['tag_name']) ? trim($data['tag_name']) : '';
        $htmlUrl = isset($data['html_url']) && \is_string($data['html_url']) ? trim($data['html_url']) : '';
        if ('' === $tag || !str_starts_with($htmlUrl, 'https://github.com/')) {
            return null;
        }

        return [
            'tag' => $tag,
            'url' => $htmlUrl,
            'at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * Count published non-prerelease releases newer than the installed semver (first page only).
     */
    private function countVersionsBehind(string $repo, string $currentNorm): ?int
    {
        try {
            $response = $this->httpClient->request('GET', self::API_HOST.'/repos/'.$repo.'/releases', [
                'headers' => $this->githubHeaders(),
                'query' => ['per_page' => self::RELEASES_PAGE_SIZE],
                'timeout' => 8,
                'max_redirects' => 0,
            ]);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return null;
            }

            /** @var list<array{tag_name?: mixed, draft?: mixed, prerelease?: mixed}> $data */
            $data = $response->toArray(false);
        } catch (\Throwable $e) {
            $this->logger?->warning('Release update versions-behind fetch failed.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'repo' => $repo,
            ]);

            return null;
        }

        $behind = 0;
        foreach ($data as $release) {
            if (true === ($release['draft'] ?? false) || true === ($release['prerelease'] ?? false)) {
                continue;
            }
            $tag = isset($release['tag_name']) && \is_string($release['tag_name']) ? $release['tag_name'] : '';
            $norm = self::parseCleanSemver($tag);
            if (null !== $norm && version_compare($norm, $currentNorm, '>')) {
                ++$behind;
            }
        }

        return $behind;
    }

    /**
     * @return array<string, string>
     */
    private function githubHeaders(): array
    {
        return [
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => '' !== trim($this->userAgent) ? $this->userAgent : 'nowo-ui-kit-release-check',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];
    }

    private function cacheKey(string $repo, string $currentNorm): string
    {
        return 'nowo_ui_kit.release_update.v1.'.hash('xxh128', $repo.'|'.$currentNorm);
    }

    /**
     * Public compare page between two refs (fixed github.com host).
     */
    private function githubCompareUrl(string $repo, string $fromRaw, string $toRaw): string
    {
        return \sprintf(
            'https://github.com/%s/compare/%s...%s',
            $repo,
            rawurlencode($this->githubTagRef($fromRaw)),
            rawurlencode($this->githubTagRef($toRaw)),
        );
    }

    /**
     * Prefer the v-prefixed tag form used by GitHub Releases.
     */
    private function githubTagRef(string $raw): string
    {
        $t = trim($raw);

        return 1 === preg_match('/^\d+\.\d+\.\d+$/', $t) ? 'v'.$t : $t;
    }

    private function parseCheckedAt(string $atom): ?\DateTimeImmutable
    {
        try {
            return new \DateTimeImmutable($atom);
        } catch (\Exception) {
            return null;
        }
    }
}
