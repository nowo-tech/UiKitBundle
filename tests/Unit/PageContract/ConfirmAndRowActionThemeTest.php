<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\ConfirmPage;
use Nowo\UiKitBundle\View\Label;
use Nowo\UiKitBundle\View\PageHeader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;

/**
 * `_confirm` buttons read the btn_* slots (and framework_defaults), type-to-confirm (Confirm::expectedValue), and
 * the row-action variant slots action_primary / action_secondary / action_danger.
 */
final class ConfirmAndRowActionThemeTest extends TestCase
{
    public function testConfirmButtonsReadTheButtonSlots(): void
    {
        $twig = PageContractTwig::create([], ['classes' => [
            'btn_secondary' => ['value' => 'app-btn', 'framework_defaults' => false],
            'btn_danger' => 'app-btn app-btn--danger',
        ]]);
        $html = $twig->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['confirm' => Confirm::delete(), 'id' => 'c1', 'action' => Action::delete('/items/1', 'tok')]);

        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--secondary app-btn" data-nowo-confirm-close>', $html);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--danger btn btn-outline-danger app-btn app-btn--danger nowo-ui-confirm__submit"', $html);
        self::assertStringNotContainsString('data-nowo-confirm-submit', $html);
        self::assertStringNotContainsString(' id="c1-form"', $html);

        // no host classes: identical to the ui.btn() output of 1.x
        $plain = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['confirm' => Confirm::delete(), 'id' => 'c1', 'action' => Action::delete('/x')]);
        self::assertStringContainsString('<button type="button" class="nowo-ui-btn nowo-ui-btn--secondary btn btn-outline-secondary" data-nowo-confirm-close>', $plain);
        self::assertStringContainsString('class="nowo-ui-btn nowo-ui-btn--danger btn btn-outline-danger nowo-ui-confirm__submit"', $plain);
    }

    public function testTypeToConfirmDialog(): void
    {
        $confirm = new Confirm('Delete project?', 'Gone for good.', expectedValue: 'acme/prod (v2)');
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['confirm' => $confirm, 'id' => 'c9', 'action' => Action::delete('/p/1', 'tok')]);

        self::assertStringContainsString('<label class="nowo-ui-confirm__expect-label" for="c9-expected">Type acme/prod (v2) to confirm</label>', $html);
        self::assertStringContainsString('<input id="c9-expected" class="nowo-ui-input form-control nowo-ui-confirm__expect-input" type="text" autocomplete="off" autocapitalize="off" spellcheck="false" required pattern="acme\/prod \(v2\)" data-nowo-confirm-expected="acme/prod (v2)" form="c9-form">', $html);
        self::assertStringContainsString('<form method="post" action="/p/1" class="nowo-ui-action__form" id="c9-form">', $html);
        self::assertMatchesRegularExpression('#<button type="submit" class="[^"]*nowo-ui-confirm__submit" data-nowo-confirm-submit disabled>Confirm</button>#', $html);

        // custom prompt label (Label params get %value%) and a CSRF FormView form
        $form = PageContractTwig::forms()->createNamedBuilder('csrf', FormType::class)->getForm()->createView();
        $labelled = new Confirm('Delete?', expectedValue: 'ACME', expectedLabel: new Label('Write %value% here', 'admin'));
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['confirm' => $labelled, 'id' => 'c8', 'action' => Action::delete('/p/2', form: $form)]);
        self::assertStringContainsString('for="c8-expected">Write ACME here</label>', $html);
        self::assertStringContainsString('id="c8-form"', $html);
        $html = PageContractTwig::create()->render('@NowoUiKitBundle/partials/_confirm.html.twig', ['confirm' => new Confirm('Delete?', expectedValue: 'ACME', expectedLabel: 'Type it'), 'id' => 'c7']);
        self::assertStringContainsString('for="c7-expected">Type it</label>', $html);
        self::assertStringNotContainsString('form="c7-form"', $html);
    }

    public function testTypeToConfirmFullPage(): void
    {
        $page = new ConfirmPage(new PageHeader('Delete'), new Confirm('Delete order?', expectedValue: 'ORDER-7', expectedLabel: new Label('Type %value%', 'admin')), Action::delete('/o/7', 'tok'));
        $html = PageContractTwig::create()->render('@NowoUiKit/page/confirm.html.twig', ['page' => $page]);

        self::assertStringContainsString('data-nowo-ui-page="confirm" data-nowo-confirm-scope', $html);
        self::assertStringContainsString('for="nowo-ui-confirm-page-expected">Type ORDER-7</label>', $html);
        self::assertStringContainsString('required pattern="ORDER-7" data-nowo-confirm-expected="ORDER-7" form="nowo-ui-confirm-page-form">', $html);
        self::assertStringContainsString('id="nowo-ui-confirm-page-form"', $html);
        // no-JS: the button is enabled, the browser enforces required + pattern
        self::assertMatchesRegularExpression('#<button type="submit" class="[^"]*nowo-ui-confirm__submit" data-nowo-confirm-submit>#', $html);

        $html = PageContractTwig::create()->render('@NowoUiKit/page/confirm.html.twig', ['page' => new ConfirmPage(new PageHeader('Delete'), new Confirm('Sure?', expectedValue: 'x'), Action::delete('/o/8'))]);
        self::assertStringContainsString('>Type x to confirm</label>', $html);
    }

    public function testRowActionVariantSlots(): void
    {
        $twig = PageContractTwig::create([], ['classes' => [
            'action' => 'row-btn',
            'action_danger' => 'row-btn--danger',
            'action_primary' => 'row-btn--primary',
            'action_secondary' => 'row-btn--muted',
        ]]);
        $objects = $twig->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => [Action::edit('/e'), Action::delete('/d', 'tok', new Confirm('Sure?', id: 'del')), new Action('restore', href: '/r', variant: 'primary'), new Action('share', href: '/s', variant: 'link')]]);
        self::assertStringContainsString('btn-sm row-btn row-btn--muted" href="/e"', $objects);
        self::assertStringContainsString('btn-sm row-btn row-btn--danger" title="Delete"', $objects);
        self::assertStringContainsString('btn-sm row-btn row-btn--primary" href="/r"', $objects);
        self::assertMatchesRegularExpression('#btn-link btn-sm row-btn" href="/s"#', $objects);

        $hashes = $twig->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => [
            ['kind' => 'delete', 'href' => '/d'],
            ['kind' => 'create', 'href' => '/c'],
            ['kind' => 'view', 'href' => '/v', 'variant' => 'danger'],
            ['kind' => 'edit', 'href' => '/e', 'variant' => 'outline'],
        ]]);
        self::assertStringContainsString('btn-sm row-btn row-btn--danger"', $hashes);
        self::assertStringContainsString('btn-sm row-btn row-btn--primary"', $hashes);
        self::assertSame(2, substr_count($hashes, 'row-btn--danger'));
        self::assertStringContainsString('btn-outline-primary btn-sm row-btn"', $hashes);

        $tailwind = PageContractTwig::create([], ['classes' => ['action_danger' => 'text-red-700']], 'tailwind')
            ->render('@NowoUiKitBundle/partials/_row_actions.html.twig', ['actions' => [Action::delete('/d')]]);
        self::assertStringContainsString('text-red-700', $tailwind);
    }
}
