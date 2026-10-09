<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Release;

use Nowo\UiKitBundle\Release\ReleaseStatusController;
use Nowo\UiKitBundle\Release\ReleaseUpdateChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ReleaseStatusControllerTest extends TestCase
{
    public function testNotFoundWhenFeatureDisabled(): void
    {
        $response = (new ReleaseStatusController())();
        self::assertSame(404, $response->getStatusCode());

        $disabled = new ReleaseUpdateChecker(new MockHttpClient(), new ArrayAdapter(), 'v1.0.0', 'acme/app', false);
        self::assertSame(404, (new ReleaseStatusController($disabled))()->getStatusCode());
    }

    public function testReturnsJsonPayloadWithNoStore(): void
    {
        $http = new MockHttpClient([new MockResponse(json_encode([
            'tag_name' => 'v1.0.0',
            'html_url' => 'https://github.com/acme/app/releases/tag/v1.0.0',
        ], \JSON_THROW_ON_ERROR), ['http_code' => 200])]);
        $checker = new ReleaseUpdateChecker($http, new ArrayAdapter(), 'v1.0.0', 'acme/app');

        $response = (new ReleaseStatusController($checker))();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('ok', $data['status']);
        self::assertSame('v1.0.0', $data['latest']);
        self::assertFalse($data['updateAvailable']);
    }
}
