<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Crumb;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;

/**
 * 2.3: `nowo_ui_page_header()` / `page/_page_header.html.twig` render the scaffolds' page header for unique pages.
 */
final class PageHeaderHelperTest extends TestCase
{
    private const PARTIAL = '<header data-p="{{ bundle }}|{{ nowo_ui_page_type }}|{{ title }}|{{ header.actions|length }}|{{ actions|length }}|{{ header_actions_rendered ? 1 : 0 }}|{{ region }}">{{ toolbar|raw }}</header>';

    public function testUniquePageRendersTheScaffoldHeader(): void
    {
        $header = new PageHeader('Board', 'Kanban', [Action::create('/cards/new')], [new Crumb('Home', '/')]);
        $twig = PageContractTwig::create([
            'unique.html.twig' => "{% extends nowo_ui_layout.admin %}{% set nowo_ui_bundle = 'NowoXBundle' %}{% block nowo_ui_page_header %}{{ nowo_ui_page_header(header) }}{% endblock %}{% block nowo_ui_content %}x{% endblock %}",
            'scaffold.html.twig' => "{% extends '@NowoUiKit/page/dashboard.html.twig' %}",
        ], [], 'bootstrap5');

        $unique = $twig->render('unique.html.twig', ['header' => $header]);
        $scaffold = $twig->render('scaffold.html.twig', ['page' => new DashboardPage($header, [], 'NowoXBundle')]);

        $extract = static fn (string $html): string => (string) preg_replace('/\s+/', ' ', substr($html, (int) strpos($html, '<div class="nowo-ui-page-header'), (int) strpos($html, '</h1>') - (int) strpos($html, '<div class="nowo-ui-page-header')));
        self::assertStringContainsString('<h1 class="nowo-ui-page-header__title h3 mb-1">Board</h1>', $unique);
        self::assertStringContainsString('data-nowo-ui-region="header"', $unique);
        self::assertStringContainsString('href="/cards/new"', $unique);
        self::assertSame($extract($scaffold), $extract($unique));
    }

    public function testOptionsAndThemePartial(): void
    {
        $twig = PageContractTwig::create([
            'kit/header.html.twig' => self::PARTIAL,
            't' => "{% set nowo_ui_page_type = 'show' %}{{ nowo_ui_page_header(header, b, extra, with) }}",
            'inc' => "{{ include('@NowoUiKit/page/_page_header.html.twig', {header: header, bundle: 'NowoYBundle'}, false) }}",
        ], ['partials' => ['page_header' => 'kit/header.html.twig']], 'none');
        $header = new PageHeader('Item', null, [Action::edit('/e')]);

        self::assertStringStartsWith('<header data-p="NowoXBundle|show|Item|1|2|1|header">', $twig->render('t', ['header' => $header, 'b' => 'NowoXBundle', 'extra' => [Action::view('/v')], 'with' => true]));
        self::assertStringStartsWith('<header data-p="|show|Item|0|1|1|header">', $twig->render('t', ['header' => $header, 'b' => null, 'extra' => new \ArrayIterator([Action::view('/v')]), 'with' => false]));
        self::assertStringStartsWith('<header data-p="NowoYBundle||Item|1|1|1|header">', $twig->render('inc', ['header' => $header]));

        $this->expectExceptionMessage('nowo_ui_page_header() extra actions: expected');
        $twig->render('t', ['header' => $header, 'b' => null, 'extra' => ['x'], 'with' => true]);
    }
}
