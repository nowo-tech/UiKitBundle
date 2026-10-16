<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Test;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\Test\RenderedPage;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\KpiTile;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\TestCase;

/**
 * Hooks of {@see PageContractTestCase} that the two contract self-tests keep at their defaults or cannot reach:
 * Form extension disabled, unreadable TEMPLATES.md.
 */
final class PageContractHooksTest extends TestCase
{
    public function testFormExtensionCanBeDisabled(): void
    {
        $case = $this->case();
        $page = $case->render(new PageFixture('@NowoUiKit/page/dashboard.html.twig', ['page' => new DashboardPage(new PageHeader('Dashboard'), [new KpiTile('Items', '42')])]));

        self::assertStringContainsString('nowo-ui-kpi__value', $page->html);
        self::assertNull($case->twig()->getFunction('form_start'));
    }

    public function testUnreadableTemplatesDocumentFails(): void
    {
        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage('docs/TEMPLATES.md not readable');
        $this->case()::unique();
    }

    public function testDefaultsAndDataProvider(): void
    {
        $case = new class('defaults') extends PageContractTestCase {
            public static function pageFixtures(): iterable
            {
                yield 'dashboard' => new PageFixture('@NowoUiKit/page/dashboard.html.twig');
            }

            /**
             * @return list<string>
             */
            public static function unique(): array
            {
                return self::uniquePages();
            }
        };

        self::assertSame([], $case::unique());
        self::assertSame(['dashboard'], array_keys(iterator_to_array($case::contractPages())));
    }

    private function case(): object
    {
        return new class('hooks') extends PageContractTestCase {
            public static function pageFixtures(): iterable
            {
                return [];
            }

            public function render(PageFixture $fixture): RenderedPage
            {
                return $this->renderPage($fixture);
            }

            public function twig(): \Twig\Environment
            {
                return $this->createTwig();
            }

            /**
             * @return list<string>
             */
            public static function unique(): array
            {
                return self::uniquePages();
            }

            protected static function formThemes(): ?array
            {
                return null;
            }

            protected static function templatesDocument(): string
            {
                return '/nonexistent/docs/TEMPLATES.md';
            }
        };
    }
}
