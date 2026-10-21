<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * What an error page shows besides its texts (2.6, built by `nowo_ui_error_page()` from the status code, the
 * exception headers and the current request — REQ-ERROR-001 item 10): the illustration, the 429 `Retry-After` wait,
 * the "Try again" link (408 / 5xx on GET), the "Go back" link (same-site referer) and the self-reload countdown of a
 * 5xx on GET. Immutable, no services (FrankenPHP worker safe).
 */
final readonly class ErrorPage
{
    /**
     * @param int|null    $retryAfter       seconds from a numeric `Retry-After` header (429 only)
     * @param string|null $retryHref        URL of the failed GET request ("Try again"), null when not retryable
     * @param string|null $backHref         same-site referer ("Go back"), null when absent or foreign
     * @param int         $autoRetrySeconds seconds before the page reloads itself (0 = never)
     */
    public function __construct(
        public int $statusCode,
        public ?ErrorIllustration $illustration = null,
        public ?int $retryAfter = null,
        public ?string $retryHref = null,
        public ?string $backHref = null,
        public int $autoRetrySeconds = 0,
        public string $homeHref = '/',
    ) {
    }

    /**
     * Whether the page reloads itself (5xx on GET with a positive countdown).
     */
    public function autoRetries(): bool
    {
        return $this->autoRetrySeconds > 0 && null !== $this->retryHref;
    }

    /**
     * Initial countdown text (`m:ss`), what the page shows before JavaScript ticks.
     */
    public function autoRetryClock(): string
    {
        return \sprintf('%d:%02d', intdiv($this->autoRetrySeconds, 60), $this->autoRetrySeconds % 60);
    }
}
