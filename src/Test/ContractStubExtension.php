<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Test;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Deterministic stand-ins for the framework Twig functions a page usually calls (`path`, `url`, `asset`,
 * `csrf_token`, `is_granted`, `logout_path`, `absolute_url`) in {@see PageContractTestCase}. A bundle overrides any
 * of them with `$twig->addFunction()` in `configureTwig()`.
 */
final class ContractStubExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('path', self::path(...)),
            new TwigFunction('url', static fn (string $name, array $parameters = []): string => 'http://localhost'.self::path($name, $parameters)),
            new TwigFunction('asset', static fn (string $path, ?string $package = null): string => '/assets/'.ltrim($path, '/')),
            new TwigFunction('csrf_token', static fn (string $id): string => 'csrf-'.$id),
            new TwigFunction('is_granted', static fn (mixed $attribute, mixed $subject = null): bool => true),
            new TwigFunction('logout_path', static fn (?string $firewall = null): string => '/logout'),
            new TwigFunction('absolute_url', static fn (string $path): string => $path),
        ];
    }

    /**
     * `/<route>` plus the parameters as a query string.
     *
     * @param array<string, mixed> $parameters
     */
    public static function path(string $name, array $parameters = []): string
    {
        return '/'.$name.([] === $parameters ? '' : '?'.http_build_query($parameters));
    }
}
