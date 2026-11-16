<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Form;

use ArrayIterator;
use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Form\ComboboxType;
use Nowo\UiKitBundle\Tests\Unit\PageContract\PageContractTwig;
use Nowo\UiKitBundle\Theme\ThemeSlots;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

/**
 * The combobox field (2.53): a plain text input without JavaScript, the `combobox` partial around it, bounded options, opt-in, and
 * stateless across requests (FrankenPHP worker mode, kernel kept alive).
 */
final class ComboboxTypeTest extends TestCase
{
    public function testItRendersAPlainInputInsideThePartial(): void
    {
        $html = $this->render($this->view(['data' => 'Home', 'attr' => ['class' => 'host', 'data-x' => '1', 'placeholder' => 'Find a page']]));

        self::assertStringContainsString('<input type="text" id="page" name="page" class="nowo-ui-input host" value="Home" autocomplete="off" required data-x="1" placeholder="Find a page">', $html);
        self::assertStringContainsString('class="nowo-ui-combobox" data-nowo-ui-combobox data-nowo-ui-combobox-url="/wiki/search?scope=all&amp;x=1" data-nowo-ui-combobox-min-length="2" data-nowo-ui-combobox-debounce="250" data-nowo-ui-combobox-param="q"', $html);
        self::assertMatchesRegularExpression('/<div id="page_combobox-listbox" class="nowo-ui-combobox__listbox" data-nowo-ui-combobox-listbox role="listbox" aria-label="Suggestions" popover="manual" hidden><\/div>/', $html);
        self::assertStringContainsString('<p class="nowo-ui-combobox__status" data-nowo-ui-combobox-status role="status"></p>', $html);
        // The roles come with the script: without it the field is a plain text box.
        self::assertStringNotContainsString('role="combobox"', $html);
        self::assertStringNotContainsString('aria-expanded', $html);
        self::assertStringNotContainsString('style=', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertSame(1, substr_count($html, ' name='), 'only the field is submitted');
    }

    public function testTheOptionsReachTheMarkupEscaped(): void
    {
        $html = $this->render($this->view(['url' => '/s?a="><x', 'min_length' => 3, 'debounce' => 0, 'param' => 'term', 'labels' => ['list' => 'Pages <b>', 'results' => '%count% hits'], 'bundle' => 'wiki']));

        self::assertStringContainsString('data-nowo-ui-combobox-url="/s?a=&quot;&gt;&lt;x"', $html);
        self::assertStringContainsString('data-nowo-ui-combobox-min-length="3"', $html);
        self::assertStringContainsString('data-nowo-ui-combobox-debounce="0"', $html);
        self::assertStringContainsString('data-nowo-ui-combobox-param="term"', $html);
        self::assertStringContainsString('aria-label="Pages &lt;b&gt;"', $html);
        self::assertStringContainsString('"results":"%count% hits"', html_entity_decode($html));
        self::assertStringNotContainsString('<x', $html);
    }

    public function testAFieldWithErrorsHelpOrDisabledSaysSo(): void
    {
        $view = $this->factory()->create(ComboboxType::class, null, ['url' => '/s', 'help' => 'Type a title'])->createView();
        $view->vars['id'] = 'page';
        $view->vars['errors'] = new ArrayIterator([1]);
        $html = $this->render($view);
        self::assertStringContainsString('aria-describedby="page_help"', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringContainsString('nowo-ui-input nowo-ui-input--invalid', $html);
        self::assertStringContainsString(' disabled', $this->render($this->view(['disabled' => true, 'required' => false])));
    }

    public function testTheValueIsTheSubmittedText(): void
    {
        $form = $this->factory()->create(ComboboxType::class, null, ['url' => '/s'])->submit('  Getting started ');

        self::assertTrue($form->isValid());
        self::assertSame('Getting started', $form->getData());
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function badOptions(): iterable
    {
        yield 'another scheme' => [['url' => 'javascript:alert(1)']];
        yield 'protocol-relative' => [['url' => '//evil.example/s']];
        yield 'backslash' => [['url' => '/\\evil.example/s']];
        yield 'relative without slash' => [['url' => 'search']];
        yield 'a space' => [['url' => '/s x']];
        yield 'min_length 0' => [['url' => '/s', 'min_length' => 0]];
        yield 'min_length 51' => [['url' => '/s', 'min_length' => 51]];
        yield 'debounce negative' => [['url' => '/s', 'debounce' => -1]];
        yield 'debounce too long' => [['url' => '/s', 'debounce' => 2001]];
        yield 'param with a quote' => [['url' => '/s', 'param' => 'q"']];
        yield 'param empty' => [['url' => '/s', 'param' => '']];
        yield 'an unknown label' => [['url' => '/s', 'labels' => ['open' => 'x']]];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('badOptions')]
    public function testBadOptionsAreRefused(array $options): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory()->create(ComboboxType::class, null, $options);
    }

    public function testTheUrlIsRequiredAndAbsoluteHttpIsAccepted(): void
    {
        self::assertSame('https://example.test/s', $this->view(['url' => 'https://example.test/s'])->vars[ComboboxType::BLOCK_PREFIX]['url']);
        $this->expectException(MissingOptionsException::class);
        $this->factory()->create(ComboboxType::class);
    }

    public function testItIsOptInAndRegistered(): void
    {
        self::assertNotContains('combobox', ThemeSlots::SCRIPTS);
        self::assertSame('@NowoUiKitBundle/partials/_combobox.html.twig', ThemeSlots::PARTIALS['combobox']);
        self::assertSame('nowo_ui_combobox', (new ComboboxType())->getBlockPrefix());

        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);
        $definition = $container->getDefinition('nowo_ui_kit.form.combobox_type');
        self::assertSame(ComboboxType::class, $definition->getClass());
        self::assertTrue($definition->hasTag('form.type'));

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
        self::assertContains(NowoUiKitExtension::COMBOBOX_FORM_THEME, $prepend->getExtensionConfig('twig')[0]['form_themes'] ?? []);
    }

    public function testTheLayoutUsesTheBlock(): void
    {
        $layout = (string) file_get_contents(\dirname(__DIR__, 3).'/src/Resources/views/form/nowo_ui_layout.html.twig');

        self::assertStringContainsString("{% use '@NowoUiKit/form/nowo_ui_combobox.html.twig' %}", $layout);
    }

    public function testTwoRendersShareNothing(): void
    {
        $type = new ComboboxType();
        $first = $this->render($this->view(['url' => '/a', 'labels' => ['list' => 'First']], $type));
        $second = $this->render($this->view(['url' => '/b'], $type));

        self::assertStringContainsString('aria-label="First"', $first);
        self::assertStringNotContainsString('First', $second);
        self::assertStringContainsString('data-nowo-ui-combobox-url="/b"', $second);
    }

    public function testThePartialWrapsAFieldOfTheHost(): void
    {
        $twig = PageContractTwig::create(['t' => "{{ include('@NowoUiKitBundle/partials/_combobox.html.twig', {id: 'x', input: '<input id=\"mine\">', url: '/s', value_input: 'mine-id', attr: {class: 'c', 'data-a': '1'}}) }}"], [], 'none');
        $html = $twig->render('t');

        self::assertStringContainsString('<input id="mine">', $html);
        self::assertStringContainsString('class="nowo-ui-combobox c"', $html);
        self::assertStringContainsString('data-a="1"', $html);
        self::assertStringContainsString('data-nowo-ui-combobox-min-length="2" data-nowo-ui-combobox-debounce="250" data-nowo-ui-combobox-value-input="mine-id" data-nowo-ui-combobox-labels=', $html);
        self::assertStringNotContainsString('data-nowo-ui-combobox-param', $html);
        self::assertStringContainsString('id="x-listbox"', $html);
    }

    public function testThePartialRefusesHandlerAttributes(): void
    {
        $twig = PageContractTwig::create(['t' => "{{ include('@NowoUiKitBundle/partials/_combobox.html.twig', {id: 'x', input: '', url: '/s', attr: {onclick: 'x()'}}) }}"], [], 'none');

        $this->expectExceptionMessage('no on* handlers');
        $twig->render('t');
    }

    /**
     * @param array<string, mixed> $options
     */
    private function view(array $options = [], ?ComboboxType $type = null): FormView
    {
        $options += ['url' => '/wiki/search?scope=all&x=1'];

        return $this->factory($type)->createNamed('page', ComboboxType::class, $options['data'] ?? null, array_diff_key($options, ['data' => true]))->createView();
    }

    private function factory(?ComboboxType $type = null): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addType($type ?? new ComboboxType())->getFormFactory();
    }

    private function render(FormView $view): string
    {
        $theme = NowoUiKitExtension::COMBOBOX_FORM_THEME;
        $twig = PageContractTwig::create(['t' => "{% form_theme field '".$theme."' %}{{ form_widget(field) }}"], [], 'none');

        return $twig->render('t', ['field' => $view]);
    }
}
