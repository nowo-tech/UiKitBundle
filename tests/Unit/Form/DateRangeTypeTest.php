<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Form;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Form\DateRangeSubmitExtension;
use Nowo\UiKitBundle\Form\DateRangeType;
use Nowo\UiKitBundle\Listing\ListingField;
use Nowo\UiKitBundle\Listing\ListingFieldState;
use Nowo\UiKitBundle\Listing\ListingLayout;
use Nowo\UiKitBundle\Tests\Unit\PageContract\PageContractTwig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Translation\Loader\ArrayLoader as TranslationArrayLoader;
use Symfony\Component\Translation\Translator;

final class DateRangeTypeTest extends TestCase
{
    public function testDefaultNamesAreSiblingsOfTheField(): void
    {
        $view = $this->filterForm()->createView();
        $range = $view['collected'];

        self::assertSame('collected_from', $range['from']->vars['full_name']);
        self::assertSame('collected_to', $range['to']->vars['full_name']);
        self::assertSame('collected_from', $range['from']->vars['id']);
        self::assertSame('date', $range['from']->vars['type']);
        self::assertSame(['from' => 'From', 'to' => 'To', 'clear' => 'Clear', 'previous' => 'Previous month', 'next' => 'Next month', 'done' => 'Done'], $range->vars[DateRangeType::BLOCK_PREFIX]['labels']);
        self::assertSame('Collected', $range->vars[DateRangeType::BLOCK_PREFIX]['placeholder'], 'humanized name');
        self::assertFalse($range->vars[DateRangeType::BLOCK_PREFIX]['translate']);
        self::assertSame([
            'class' => 'nowo-ui-date-range',
            'data-controller' => 'date-range',
            'data-date-range-labels-value' => '{"from":"From","to":"To","clear":"Clear","previous":"Previous month","next":"Next month","done":"Done"}',
        ], $range->vars['attr']);
    }

    public function testHostNamesUnderARootName(): void
    {
        $form = $this->factory()->createNamedBuilder('filter')
            ->add('collection_date', DateRangeType::class, ['from_name' => 'min_receipt_collection_date', 'to_name' => 'max_receipt_collection_date'])
            ->getForm();
        $range = $form->createView()['collection_date'];

        self::assertSame('filter[min_receipt_collection_date]', $range['from']->vars['full_name']);
        self::assertSame('filter[max_receipt_collection_date]', $range['to']->vars['full_name']);
        self::assertSame('min_receipt_collection_date', $range->vars[DateRangeType::BLOCK_PREFIX]['from_name']);

        $form->submit(['min_receipt_collection_date' => '2026-02-01', 'max_receipt_collection_date' => '2026-02-15']);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid());
        self::assertSame(['from' => '2026-02-01', 'to' => '2026-02-15'], $form->get('collection_date')->getData());
    }

    public function testRootlessGetRequestWithTheHostsQueryNames(): void
    {
        $form = $this->filterForm(['from_name' => 'created_at_from', 'to_name' => 'created_at_to']);
        $form->handleRequest(Request::create('/items', 'GET', ['created_at_from' => '2026-01-01', 'created_at_to' => '', 'q' => 'abc']));

        self::assertTrue($form->isSubmitted());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));
        self::assertSame(['from' => '2026-01-01', 'to' => null], $form->get('collected')->getData());
        self::assertSame('abc', $form->get('q')->getData());
        $view = $form->createView();
        self::assertSame('2026-01-01', $view['collected']['from']->vars['value']);
        self::assertSame(['from' => '2026-01-01', 'to' => ''], $view['collected']->vars['value']);
    }

    public function testNestedValuesStillWorkAndFlatOnesWin(): void
    {
        $form = $this->filterForm();
        $form->submit(['collected' => ['from' => '2026-03-01', 'to' => '2026-03-31'], 'collected_to' => '2026-03-15']);

        self::assertSame(['from' => '2026-03-01', 'to' => '2026-03-15'], $form->get('collected')->getData());
    }

    public function testNothingSubmittedLeavesTheRangeEmpty(): void
    {
        $form = $this->filterForm();
        $form->submit(['q' => 'x']);

        self::assertTrue($form->isValid());
        self::assertSame(['from' => null, 'to' => null], $form->get('collected')->getData());
    }

    public function testAnInvalidDayIsAnErrorOfTheRange(): void
    {
        $form = $this->filterForm();
        $form->submit(['collected_from' => 'not-a-date']);

        self::assertFalse($form->get('collected')->isValid());
        self::assertCount(1, $form->get('collected')->getErrors());
    }

    public function testSubmitListenerIgnoresScalarData(): void
    {
        $form = $this->filterForm();
        $event = new FormEvent($form, 'scalar');
        DateRangeSubmitExtension::mapRanges($event);

        self::assertSame('scalar', $event->getData());
        self::assertSame([FormType::class], [...DateRangeSubmitExtension::getExtendedTypes()]);
    }

    public function testTranslatedLabelsPlaceholderAndPickerValues(): void
    {
        $view = $this->factory($this->translator())->createNamedBuilder('')
            ->add('collected', DateRangeType::class, [
                'placeholder' => 'receipt.filter.collection_date',
                'attr' => ['class' => 'app-range', 'data-controller' => 'other', 'placeholder' => 'ignored'],
                'from_options' => ['attr' => ['min' => '2020-01-01']],
                'picker' => ['months' => 2, 'first_day_of_week' => 0, 'locale' => 'es_ES', 'labels' => ['done' => 'Aplicar']],
            ])
            ->getForm()
            ->createView()['collected'];

        self::assertSame([
            'class' => 'app-range nowo-ui-date-range',
            'data-controller' => 'other date-range',
            'data-date-range-labels-value' => '{"done":"Aplicar","from":"Desde","to":"Hasta","clear":"Borrar","previous":"Mes anterior","next":"Mes siguiente"}',
            'data-date-range-months-value' => '2',
            'data-date-range-first-day-of-week-value' => '0',
            'data-date-range-locale-value' => 'es_ES',
        ], $view->vars['attr']);
        self::assertSame('receipt.filter.collection_date', $view->vars[DateRangeType::BLOCK_PREFIX]['placeholder']);
        self::assertTrue($view->vars[DateRangeType::BLOCK_PREFIX]['translate']);
        self::assertSame('2020-01-01', $view['from']->vars['attr']['min']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function placeholders(): iterable
    {
        yield 'attr placeholder' => [['attr' => ['placeholder' => 'filter.created']], 'filter.created'];
        yield 'label' => [['label' => 'Created'], 'Created'];
        yield 'label false' => [['label' => false], 'Collected'];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('placeholders')]
    public function testPlaceholderFallbacks(array $options, string $expected): void
    {
        $view = $this->factory()->createNamed('collected', DateRangeType::class, null, $options)->createView();

        self::assertSame($expected, $view->vars[DateRangeType::BLOCK_PREFIX]['placeholder']);
        self::assertArrayNotHasKey('placeholder', $view->vars['attr']);
    }

    public function testPickerOffKeepsTwoNativeInputs(): void
    {
        $view = $this->factory()->createNamed('collected', DateRangeType::class, null, ['picker' => false, 'from_label' => 'Since', 'to_label' => 'Until'])->createView();

        self::assertSame(['class' => 'nowo-ui-date-range'], $view->vars['attr']);
        self::assertNull($view->vars[DateRangeType::BLOCK_PREFIX]['controller']);
        self::assertSame('Since', $view->vars[DateRangeType::BLOCK_PREFIX]['labels']['from']);

        $html = $this->render($view, standalone: true);
        self::assertStringNotContainsString('data-controller', $html);
        self::assertStringNotContainsString('-target', $html);
        self::assertStringContainsString('<span class="nowo-ui-date-range__short">Since</span>', $html);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'same names' => [['from_name' => 'day', 'to_name' => 'day']];
        yield 'name with brackets' => [['from_name' => 'day[from]']];
        yield 'unknown picker key' => [['picker' => ['range' => true]]];
        yield 'bad controller' => [['picker' => ['controller' => 'Date Range']]];
        yield 'months' => [['picker' => ['months' => 4]]];
        yield 'months not int' => [['picker' => ['months' => '2']]];
        yield 'first day' => [['picker' => ['first_day_of_week' => 7]]];
        yield 'locale' => [['picker' => ['locale' => 'es-<script>']]];
        yield 'locale not string' => [['picker' => ['locale' => 3]]];
        yield 'labels unknown' => [['picker' => ['labels' => ['from' => 'x']]]];
        yield 'labels not strings' => [['picker' => ['labels' => ['clear' => 1]]]];
        yield 'labels not array' => [['picker' => ['labels' => 'x']]];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidOptions')]
    public function testRejectsInvalidOptions(array $options): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory()->createNamed('day', DateRangeType::class, null, $options);
    }

    public function testNormalizePicker(): void
    {
        self::assertFalse(DateRangeType::normalizePicker(false));
        self::assertSame(['controller' => 'date-range', 'months' => 0, 'first_day_of_week' => 1, 'locale' => null, 'labels' => []], DateRangeType::normalizePicker(true));
        self::assertSame('nowo-ui--date-range', DateRangeType::normalizePicker(['controller' => 'nowo-ui--date-range'])['controller'] ?? null);
    }

    public function testRendersTheGroupWithTheUiKitFormTheme(): void
    {
        $view = $this->factory()->createNamedBuilder('')
            ->add('collected', DateRangeType::class, ['placeholder' => 'Collection date', 'data' => ['from' => '2026-02-01', 'to' => null]])
            ->getForm()
            ->createView();

        $html = $this->render($view['collected']);

        self::assertMatchesRegularExpression('/<div id="collected" class="nowo-ui-date-range" data-controller="date-range" data-date-range-labels-value="[^"]+" role="group" aria-label="Collection date" data-nowo-ui-filter-group="" data-date-range-placeholder-value="Collection date" data-date-range-locale-value="en">/', $html);
        self::assertStringContainsString('<div class="nowo-ui-date-range__parts" data-date-range-target="parts">', $html);
        self::assertStringContainsString('<label class="nowo-ui-date-range__part nowo-ui-date-range__part--from" for="collected_from">', $html);
        self::assertStringContainsString('<span class="nowo-ui-date-range__short">From</span>', $html);
        self::assertMatchesRegularExpression('/<input type="date" id="collected_from" name="collected_from" aria-label="Collection date: From" data-date-range-target="from" class="nowo-ui-input" value="2026-02-01"/', $html);
        self::assertMatchesRegularExpression('/<input type="date" id="collected_to" name="collected_to" aria-label="Collection date: To" data-date-range-target="to" class="nowo-ui-input"/', $html);
    }

    public function testStandaloneThemeTranslatesThePlaceholderInTheFieldsDomain(): void
    {
        $view = $this->factory()->createNamedBuilder('', FormType::class, null, ['translation_domain' => 'app'])
            ->add('collected', DateRangeType::class, ['placeholder' => 'filter.collected'])
            ->getForm()
            ->createView();

        $html = $this->render($view['collected'], standalone: true, messages: ['app' => ['filter.collected' => 'Fecha de cobro']]);

        self::assertStringContainsString('aria-label="Fecha de cobro"', $html);
        self::assertStringContainsString('aria-label="Fecha de cobro: From"', $html);
        self::assertStringContainsString('<input type="date" id="collected_from" name="collected_from"', $html, 'form_div_layout widget');
    }

    public function testListingFilterFieldsAndTheFoldedCardCountTheRangeOnce(): void
    {
        $form = $this->filterForm(['placeholder' => 'Collected']);
        $form->submit(['collected_from' => '2026-02-01', 'collected_to' => '2026-02-15', 'q' => 'abc']);
        $layout = new ListingLayout('items', [], [
            new ListingFieldState(new ListingField('q', 'Search'), true),
            new ListingFieldState(new ListingField('collected', 'Collected', queryKeys: ['collected_from', 'collected_to']), true),
        ]);

        $twig = PageContractTwig::create([
            't' => "{% form_theme form '@NowoUiKit/form/nowo_ui_layout.html.twig' %}"
                ."{{ include('@NowoUiKitBundle/partials/_filters_collapse.html.twig', {filter_form: form, fold_key: 'items', folded: true, testids: true}, false) }}"
                ."{{ include('@NowoUiKitBundle/partials/_listing_filter_fields.html.twig', {form: form, listing_layout: layout}, false) }}",
        ], [], 'none');
        $html = $twig->render('t', ['form' => $form->createView(), 'layout' => $layout]);

        self::assertStringContainsString('data-testid="filters-collapse-summary">2 active filters</p>', $html, 'search + the range (once)');
        self::assertStringContainsString('name="collected_from"', $html);
        self::assertStringContainsString('value="2026-02-15"', $html);
    }

    public function testRegisteredWithSymfonyFormAndThemedForEveryForm(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);

        $type = $container->getDefinition('nowo_ui_kit.form.date_range_type');
        self::assertSame(DateRangeType::class, $type->getClass());
        self::assertTrue($type->hasTag('form.type'));
        self::assertSame('translator', (string) $type->getArgument(0));
        self::assertTrue($container->getDefinition('nowo_ui_kit.form.date_range_submit_extension')->hasTag('form.type_extension'));

        $prepend = new ContainerBuilder();
        $prepend->registerExtension(new class extends Extension {
            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return 'twig';
            }
        });
        $prepend->prependExtensionConfig(Configuration::ALIAS, []);
        (new NowoUiKitExtension())->prepend($prepend);
        self::assertSame([NowoUiKitExtension::DATE_RANGE_FORM_THEME], $prepend->getExtensionConfig('twig')[0]['form_themes'] ?? null);
    }

    public function testATextSiblingIsNotARange(): void
    {
        $form = $this->factory()->createNamedBuilder('')->add('collected_from', TextType::class)->getForm();
        $form->submit(['collected_from' => 'x']);

        self::assertSame('x', $form->get('collected_from')->getData());
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return \Symfony\Component\Form\FormInterface<mixed>
     */
    private function filterForm(array $options = []): \Symfony\Component\Form\FormInterface
    {
        return $this->factory()->createNamedBuilder('', FormType::class, null, ['method' => 'GET'])
            ->add('q', TextType::class, ['required' => false])
            ->add('collected', DateRangeType::class, $options)
            ->getForm();
    }

    private function factory(?Translator $translator = null): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addType(new DateRangeType($translator))
            ->addTypeExtension(new DateRangeSubmitExtension())
            ->getFormFactory();
    }

    private function translator(): Translator
    {
        $translator = new Translator('es');
        $translator->addLoader('array', new TranslationArrayLoader());
        $translator->addResource('array', [
            'date_range.from' => 'Desde',
            'date_range.to' => 'Hasta',
            'date_range.clear' => 'Borrar',
            'date_range.previous' => 'Mes anterior',
            'date_range.next' => 'Mes siguiente',
            'date_range.done' => 'Listo',
        ], 'es', 'NowoUiKitBundle');

        return $translator;
    }

    /**
     * @param array<string, array<string, string>> $messages
     */
    private function render(FormView $view, bool $standalone = false, array $messages = []): string
    {
        $theme = $standalone ? NowoUiKitExtension::DATE_RANGE_FORM_THEME : '@NowoUiKit/form/nowo_ui_layout.html.twig';
        $twig = PageContractTwig::create(['t' => "{% form_theme field '".$theme."' %}{{ form_widget(field) }}"], [], 'none', messages: $messages);

        return $twig->render('t', ['field' => $view]);
    }
}
