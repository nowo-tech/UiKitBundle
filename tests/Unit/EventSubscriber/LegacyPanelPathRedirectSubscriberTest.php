<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\EventSubscriber;

use Nowo\UiKitBundle\EventSubscriber\LegacyPanelPathRedirectSubscriber;
use Nowo\UiKitBundle\Routing\PanelPathRewriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class LegacyPanelPathRedirectSubscriberTest extends TestCase
{
    public function testSubscribesToKernelRequest(): void
    {
        self::assertSame(
            [KernelEvents::REQUEST => ['onKernelRequest', 32]],
            LegacyPanelPathRedirectSubscriber::getSubscribedEvents(),
        );
    }

    public function testRedirectsLegacyPathPreservingQuery(): void
    {
        $event = $this->dispatch(Request::create('/admin/blog/3?page=2'));

        $response = $event->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(301, $response->getStatusCode());
        self::assertSame('/panel/blog/3?page=2', $response->headers->get('Location'));
    }

    public function testRedirectWithoutQueryString(): void
    {
        $response = $this->dispatch(Request::create('/admin/blog'))->getResponse();

        self::assertSame('/panel/blog', $response?->headers->get('Location'));
    }

    public function testNonSafeMethodUses308(): void
    {
        $response = $this->dispatch(Request::create('/admin/blog', 'POST'))->getResponse();

        self::assertSame(308, $response?->getStatusCode());
    }

    public function testIgnoresUnmappedPaths(): void
    {
        self::assertNull($this->dispatch(Request::create('/panel/blog'))->getResponse());
    }

    public function testIgnoresSubRequests(): void
    {
        self::assertNull($this->dispatch(Request::create('/admin/blog'), HttpKernelInterface::SUB_REQUEST)->getResponse());
    }

    public function testEmptyMapNeverRedirects(): void
    {
        $subscriber = new LegacyPanelPathRedirectSubscriber(new PanelPathRewriter());
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), Request::create('/admin/blog'), HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    private function dispatch(Request $request, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        $subscriber = new LegacyPanelPathRedirectSubscriber(new PanelPathRewriter(['/admin/blog' => '/panel/blog']));
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, $type);
        $subscriber->onKernelRequest($event);

        return $event;
    }
}
