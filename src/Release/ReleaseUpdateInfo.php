<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Release;

/**
 * Result of comparing the installed version to the latest public GitHub release (cached).
 */
final readonly class ReleaseUpdateInfo
{
    public const STATUS_OK = 'ok';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_ERROR = 'error';

    public function __construct(
        public string $status,
        public string $current,
        public ?string $latest = null,
        public bool $updateAvailable = false,
        public ?int $versionsBehind = null,
        public ?string $releaseUrl = null,
        public ?string $compareUrl = null,
        public ?\DateTimeImmutable $checkedAt = null,
    ) {
    }

    /**
     * @return array{
     *     status: string,
     *     current: string,
     *     latest: ?string,
     *     updateAvailable: bool,
     *     versionsBehind: ?int,
     *     releaseUrl: ?string,
     *     compareUrl: ?string,
     *     checkedAt: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'current' => $this->current,
            'latest' => $this->latest,
            'updateAvailable' => $this->updateAvailable,
            'versionsBehind' => $this->versionsBehind,
            'releaseUrl' => $this->releaseUrl,
            'compareUrl' => $this->compareUrl,
            'checkedAt' => $this->checkedAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
