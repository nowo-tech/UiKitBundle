<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Test;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\View\AuthPage;
use Nowo\UiKitBundle\View\Link;
use Symfony\Bridge\Twig\Extension\FormExtension;

/**
 * The auth scaffold without symfony/form (no Form extension) and without a form: renders, no form markup (2.3).
 */
final class AuthWithoutFormContractTest extends PageContractTestCase
{
    public static function pageFixtures(): iterable
    {
        yield 'auth without form' => new PageFixture('@NowoUiKit/page/auth.html.twig', ['page' => new AuthPage('Maintenance', intro: 'Back soon.', links: [new Link('Status', '/status')])]);
    }

    public function testRendersWithoutTheFormExtension(): void
    {
        $twig = $this->createTwig();
        self::assertFalse($twig->hasExtension(FormExtension::class));
        $html = $this->renderPage(new PageFixture('@NowoUiKit/page/auth.html.twig', ['page' => new AuthPage('Maintenance')]))->html;
        self::assertStringContainsString('Maintenance', $html);
        self::assertStringNotContainsString('<form', $html);
    }

    protected static function formThemes(): ?array
    {
        return null;
    }
}
