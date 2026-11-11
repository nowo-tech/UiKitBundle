<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\View;

/**
 * Optional host SPI (2.47): the illustration of an error page per status code, e.g. per-brand art from a CDN or from
 * settings already in memory. Point `nowo_ui_kit.error_pages.illustration_provider` at your service id.
 *
 * Return an {@see ErrorIllustration} (usually {@see ErrorIllustration::fromUrl()}), or null to use the configured
 * `error_pages.illustrations` map (then its `default` entry, then no illustration).
 *
 * Contract, because an error page often renders while the database, cache or network is down:
 * - be fast and never need the database (read config, a precomputed map or memory; no query, no HTTP call);
 * - never keep request / user / tenant data in properties or statics across requests (FrankenPHP worker mode with the
 *   kernel kept alive); resolve per call.
 *
 * UiKit guards every call: any `Throwable` (including a TypeError or an invalid {@see ErrorIllustration}) falls back to
 * the configured map and logs one warning when a logger is available; `error_pages.illustration_provider_budget_ms`
 * skips the provider for the rest of the same request once one call ran over budget.
 */
interface ErrorIllustrationProviderInterface
{
    /**
     * @param int $statusCode HTTP status of the page (0 when unknown)
     */
    public function getIllustration(int $statusCode): ?ErrorIllustration;
}
