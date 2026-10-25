<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Form;

use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Form\MultiSelectTypeExtension;
use Nowo\UiKitBundle\Tests\Unit\PageContract\PageContractTwig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Translation\Loader\ArrayLoader as TranslationArrayLoader;
use Symfony\Component\Translation\Translator;

final class MultiSelectTypeExtensionTest extends TestCase
{
    private const STATUSES = ['Active' => 'active', 'Cancelled' => 'cancelled', 'Pending' => 'pending'];

    public function testOffByDefault(): void
    {
        $view = $this->factory()->createNamed('status', ChoiceType::class, null, ['choices' => self::STATUSES, 'multiple' => true])->createView();

        self::assertSame([], $view->vars['attr']);
        self::assertArrayNotHasKey(MultiSelectTypeExtension::OPTION, $view->vars);
    }

    public function testAddsThePeerMarkupWithTranslatedLabels(): void
    {
        $view = $this->factory($this->translator('es'))->createNamed('status', ChoiceType::class, null, [
            'choices' => self::STATUSES,
            'multiple' => true,
            'required' => false,
            'attr' => ['class' => 'app-filter', 'data-controller' => 'other', 'placeholder' => 'Any status'],
            MultiSelectTypeExtension::OPTION => true,
        ])->createView();

        self::assertSame([
            'class' => 'app-filter nowo-ui-multi-select',
            'data-controller' => 'other multi-select',
            'placeholder' => 'Any status',
            'data-multi-select-labels-value' => '{"remove":"Quitar","noResults":"Sin resultados"}',
        ], $view->vars['attr']);
        self::assertTrue($view->vars[MultiSelectTypeExtension::OPTION]);
        self::assertSame('status[]', $view->vars['full_name']);
    }

    public function testOptionsControllerLabelsAndDropdownParent(): void
    {
        $view = $this->factory()->createNamed('type', ChoiceType::class, null, [
            'choices' => self::STATUSES,
            'multiple' => true,
            'attr' => ['class' => 'nowo-ui-multi-select', 'data-controller' => 'nowo-ui--multi-select'],
            MultiSelectTypeExtension::OPTION => [
                'controller' => 'nowo-ui--multi-select',
                'options' => ['maxItems' => 2, 'placeholder' => 'Ninguno / «todos»'],
                'labels' => ['remove' => 'Drop'],
                'dropdown_parent' => 'inline',
            ],
        ])->createView();

        self::assertSame([
            'class' => 'nowo-ui-multi-select',
            'data-controller' => 'nowo-ui--multi-select',
            'data-nowo-ui--multi-select-labels-value' => '{"remove":"Drop","noResults":"No results"}',
            'data-nowo-ui--multi-select-options-value' => '{"maxItems":2,"placeholder":"Ninguno / «todos»"}',
            'data-nowo-ui--multi-select-dropdown-parent-value' => 'inline',
        ], $view->vars['attr']);
    }

    public function testExpandedChoicesAreLeftAlone(): void
    {
        $view = $this->factory()->createNamed('status', ChoiceType::class, null, ['choices' => self::STATUSES, 'multiple' => true, 'expanded' => true, MultiSelectTypeExtension::OPTION => true])->createView();

        self::assertSame([], $view->vars['attr']);
    }

    public function testASingleCollapsedChoiceIsEnhancedToo(): void
    {
        $view = $this->factory()->createNamed('owner', ChoiceType::class, null, ['choices' => self::STATUSES, MultiSelectTypeExtension::OPTION => ['dropdown_parent' => 'body']])->createView();

        self::assertSame('multi-select', $view->vars['attr']['data-controller']);
        self::assertSame('body', $view->vars['attr']['data-multi-select-dropdown-parent-value']);
    }

    public function testGetRequestWithArrayValues(): void
    {
        $form = $this->factory()->createNamedBuilder('', options: ['method' => 'GET'])
            ->add('status', ChoiceType::class, ['choices' => self::STATUSES, 'multiple' => true, 'required' => false, MultiSelectTypeExtension::OPTION => true])
            ->getForm();
        $form->submit(['status' => ['active', 'pending']]);

        self::assertTrue($form->isSynchronized());
        self::assertSame(['active', 'pending'], $form->get('status')->getData());
        $options = $form->createView()['status']->vars;
        self::assertSame(['active', 'pending'], $options['value']);
    }

    public function testRendersTheSelectWithTheUiKitFormTheme(): void
    {
        $factory = $this->factory();
        $view = $factory->createNamedBuilder('')
            ->add('status', ChoiceType::class, ['choices' => self::STATUSES, 'multiple' => true, 'required' => false, 'data' => ['pending'], 'attr' => ['placeholder' => 'Any'], MultiSelectTypeExtension::OPTION => true])
            ->getForm()
            ->createView();

        $html = $this->renderWidget($view['status']);

        self::assertStringContainsString('<select id="status" name="status[]"', $html);
        self::assertStringContainsString('placeholder="Any"', $html);
        self::assertStringContainsString('data-controller="multi-select"', $html);
        self::assertStringContainsString('data-multi-select-labels-value="{&quot;remove&quot;:&quot;Remove&quot;,&quot;noResults&quot;:&quot;No results&quot;}"', $html);
        self::assertStringContainsString('multiple="multiple"', $html);
        self::assertMatchesRegularExpression('/class="nowo-ui-multi-select nowo-ui-input"/', $html);
        self::assertStringContainsString('<option value="pending" selected="selected">Pending</option>', $html);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidValues(): iterable
    {
        yield 'unknown key' => [['plugins' => []]];
        yield 'bad controller' => [['controller' => 'Multi Select']];
        yield 'controller not a string' => [['controller' => 3]];
        yield 'options as a list' => [['options' => ['remove_button']]];
        yield 'options not an array' => [['options' => 'x']];
        yield 'labels unknown key' => [['labels' => ['clear' => 'x']]];
        yield 'labels not strings' => [['labels' => ['remove' => 1]]];
        yield 'labels not an array' => [['labels' => 'x']];
        yield 'dropdown parent' => [['dropdown_parent' => 'window']];
    }

    #[DataProvider('invalidValues')]
    public function testRejectsInvalidValues(mixed $value): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory()->createNamed('status', ChoiceType::class, null, ['choices' => self::STATUSES, 'multiple' => true, MultiSelectTypeExtension::OPTION => $value]);
    }

    public function testNormalize(): void
    {
        self::assertFalse(MultiSelectTypeExtension::normalize(false));
        self::assertSame(['controller' => 'multi-select', 'options' => [], 'labels' => [], 'dropdown_parent' => 'auto'], MultiSelectTypeExtension::normalize(true));
        self::assertSame([ChoiceType::class], [...MultiSelectTypeExtension::getExtendedTypes()]);
    }

    public function testRegisteredWithSymfonyForm(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);

        $definition = $container->getDefinition('nowo_ui_kit.form.multi_select_extension');
        self::assertSame(MultiSelectTypeExtension::class, $definition->getClass());
        self::assertTrue($definition->hasTag('form.type_extension'));
        self::assertSame('translator', (string) $definition->getArgument(0));
    }

    private function factory(?Translator $translator = null): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addTypeExtension(new MultiSelectTypeExtension($translator))
            ->getFormFactory();
    }

    private function translator(string $locale): Translator
    {
        $translator = new Translator($locale);
        $translator->addLoader('array', new TranslationArrayLoader());
        $translator->addResource('array', ['multi_select.remove' => 'Quitar', 'multi_select.no_results' => 'Sin resultados'], $locale, 'NowoUiKitBundle');

        return $translator;
    }

    private function renderWidget(FormView $view): string
    {
        $twig = PageContractTwig::create(['t' => "{% form_theme field '@NowoUiKit/form/nowo_ui_layout.html.twig' %}{{ form_widget(field) }}"], [], 'none');

        return $twig->render('t', ['field' => $view]);
    }
}
