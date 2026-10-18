<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Test;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\View\DashboardPage;
use Nowo\UiKitBundle\View\KpiTile;
use Nowo\UiKitBundle\View\PageHeader;

/**
 * 2.3 default (strict mode is opt-in until 3.0): only key-looking ids count as missing; a literal sent through
 * `|trans` passes.
 */
final class DefaultMissingTranslationsTest extends PageContractTestCase
{
    private const TEMPLATE = '@NowoContractDefault/page.html.twig';

    public static function pageFixtures(): iterable
    {
        yield 'literal through trans' => new PageFixture(self::TEMPLATE, ['page' => new DashboardPage(new PageHeader('Default'), [new KpiTile('Items', '1')]), 'contract_only' => true]);
    }

    public function testOnlyDottedIdsAreMissingByDefault(): void
    {
        self::assertFalse(self::strictMissingTranslations());
        $page = $this->renderPage(new PageFixture(self::TEMPLATE, ['page' => new DashboardPage(new PageHeader('Default'), [new KpiTile('Items', '1')])]));
        self::assertStringContainsString('Name or e-mail', $page->html);
        self::assertSame(['NowoUiKitBundle:fixture.missing_key'], $page->missingTranslations);
    }

    protected static function inlineTemplates(): array
    {
        return [self::TEMPLATE => "{% extends '@NowoUiKit/page/dashboard.html.twig' %}{% block nowo_ui_dashboard_cards %}<p>{{ 'Name or e-mail'|trans }}</p>{% if not (contract_only ?? false) %}{{ 'fixture.missing_key'|trans({}, 'NowoUiKitBundle') }}{% endif %}{% endblock %}"];
    }
}
