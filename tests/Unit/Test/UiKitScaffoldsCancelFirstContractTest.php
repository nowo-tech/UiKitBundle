<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Test;

use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;

/**
 * 2.66.1: the shared contract test against UiKit's own scaffolds with the stub theme set to
 * `theme.form_actions_order: cancel_first` ({@see PageContractTestCase::formActionsOrder()}): the form scaffold renders
 * Cancel first and the opt-in order rule follows the configured order.
 */
final class UiKitScaffoldsCancelFirstContractTest extends PageContractTestCase
{
    /**
     * @return array<string, PageFixture>
     */
    public static function pageFixtures(): array
    {
        $templates = [];
        foreach (self::SCAFFOLDS as $scaffold) {
            $templates[$scaffold] = '@NowoUiKit/page/'.$scaffold.'.html.twig';
        }

        return ScaffoldFixtures::all($templates);
    }

    public function testTheFormScaffoldPutsCancelFirst(): void
    {
        self::assertSame('cancel_first', self::configuredFormActionsOrder());
        $page = $this->renderPage(self::pageFixtures()['form']);
        self::assertSame([['cancel', 'primary']], $page->formActionsRows());
        $this->assertFormActionsCancelFirst($page);
        $this->assertFormActionsOrder($page);
    }

    protected static function strictMissingTranslations(): bool
    {
        return true;
    }

    protected static function formActionsOrderChecked(): bool
    {
        return true;
    }

    protected static function formActionsOrder(): ?string
    {
        return 'cancel_first';
    }
}
