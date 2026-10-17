<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\View\Action;
use Nowo\UiKitBundle\View\Confirm;
use Nowo\UiKitBundle\View\FormPage;
use Nowo\UiKitBundle\View\PageHeader;
use Nowo\UiKitBundle\View\ShowPage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormView;

/**
 * Header actions backed by a CSRF-only FormView keep their hidden fields (the CSRF token) however often the
 * FormView is rendered: the scaffolds pre-render the 1.x `toolbar` (marking the FormView rendered) and a host
 * `page_header` override that renders `actions` renders the same FormView again.
 */
final class HeaderActionFormTokenTest extends TestCase
{
    private const OVERRIDE = <<<'TWIG'
        <header data-host-header>
            {% for action in actions|default(header.actions) %}
                {{ include(nowo_ui_partial('action', bundle|default(null)), {action: action, context: 'toolbar', bundle: bundle|default(null)}, false) }}
            {% endfor %}
        </header>
        TWIG;

    /**
     * @return iterable<string, array{0: bool}>
     */
    public static function partials(): iterable
    {
        yield 'default partial' => [false];
        yield 'override rendering actions' => [true];
    }

    #[DataProvider('partials')]
    public function testShowHeaderFormActionsKeepTheirTokenOnce(bool $override): void
    {
        $page = new ShowPage(new PageHeader('Item', null, [
            new Action('restore', 'Restore', '/items/1/restore', method: 'PATCH', form: $this->csrfForm('restore_item', 'tok-restore')),
            Action::delete('/items/1', form: $this->csrfForm('delete_item', 'tok-delete')),
        ]), []);

        $html = $this->twig($override)->render('@NowoUiKit/page/show.html.twig', ['page' => $page]);

        self::assertSame($override ? 1 : 0, substr_count($html, 'data-host-header'));
        $this->assertFormHasHiddenOnce($html, 'restore_item', 'restore_item[_token]', 'tok-restore');
        $this->assertFormHasHiddenOnce($html, 'delete_item', 'delete_item[_token]', 'tok-delete');
        self::assertStringContainsString('<input type="hidden" name="_method" value="PATCH" />', $html);
    }

    #[DataProvider('partials')]
    public function testFormPageSecondaryAndConfirmedFormActionsKeepTheirToken(bool $override): void
    {
        $page = new FormPage(
            new PageHeader('Edit item', null, [new Action('restore', 'Restore', '/items/1/restore', method: 'POST', confirm: Confirm::delete(), form: $this->csrfForm('restore_item', 'tok-restore'))]),
            $this->entityForm(),
            secondaryActions: [new Action('copy', 'Duplicate', '/items/1/copy', method: 'POST', form: $this->csrfForm('copy_item', 'tok-copy'))],
            secondaryActionsInHeader: true,
        );

        $html = $this->twig($override)->render('@NowoUiKit/page/form.html.twig', ['page' => $page]);

        $this->assertFormHasHiddenOnce($html, 'restore_item', 'restore_item[_token]', 'tok-restore');
        $this->assertFormHasHiddenOnce($html, 'copy_item', 'copy_item[_token]', 'tok-copy');
    }

    public function testOneFormViewSharedByRowsKeepsItsTokenInEveryRow(): void
    {
        $shared = $this->csrfForm('delete_item', 'tok-shared');
        $actions = [Action::delete('/items/1', form: $shared), Action::delete('/items/2', form: $shared)];

        $html = PageContractTwig::create(['t' => "{% for a in actions %}{% include '@NowoUiKitBundle/partials/_row_actions.html.twig' with {actions: [a]} only %}{% endfor %}"])->render('t', ['actions' => $actions]);

        $forms = $this->forms($html, 'delete_item');
        self::assertCount(2, $forms);
        foreach ($forms as $form) {
            self::assertSame(1, substr_count($form, 'name="delete_item[_token]" value="tok-shared"'), $form);
        }
    }

    public function testNonHiddenChildrenRenderOnlyOnFirstRender(): void
    {
        $view = PageContractTwig::forms()->createNamedBuilder('note', FormType::class)
            ->add('_token', HiddenType::class, ['data' => 'tok'])
            ->add('reason', TextType::class, ['required' => false])
            ->getForm()->createView();
        $action = new Action('custom', 'Note', '/n', method: 'POST', form: $view);

        $html = PageContractTwig::create(['t' => "{% for i in 1..2 %}{% include '@NowoUiKitBundle/partials/_action.html.twig' with {action: a} only %}{% endfor %}"])->render('t', ['a' => $action]);

        self::assertSame(2, substr_count($html, 'name="note[_token]" value="tok"'));
        self::assertSame(1, substr_count($html, 'name="note[reason]"'));
    }

    private function assertFormHasHiddenOnce(string $html, string $formName, string $field, string $value): void
    {
        $forms = $this->forms($html, $formName);
        self::assertNotSame([], $forms, \sprintf('No <form name="%s"> rendered.', $formName));
        foreach ($forms as $form) {
            self::assertSame(1, substr_count($form, \sprintf('name="%s" value="%s"', $field, $value)), \sprintf('Form "%s" must carry %s exactly once: %s', $formName, $field, $form));
        }
    }

    /**
     * @return list<string>
     */
    private function forms(string $html, string $name): array
    {
        preg_match_all(\sprintf('#<form name="%s"[^>]*>.*?</form>#s', preg_quote($name, '#')), $html, $matches);

        return $matches[0];
    }

    private function twig(bool $override): \Twig\Environment
    {
        return $override
            ? PageContractTwig::create(['host/_page_header.html.twig' => self::OVERRIDE], ['partials' => ['page_header' => 'host/_page_header.html.twig']])
            : PageContractTwig::create();
    }

    private function csrfForm(string $name, string $token): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder($name, FormType::class)->add('_token', HiddenType::class, ['data' => $token])->getForm()->createView();
    }

    private function entityForm(): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('item', FormType::class)->add('name', TextType::class, ['required' => false])->getForm()->createView();
    }
}
