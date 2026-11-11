<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Form;

use InvalidArgumentException;
use LogicException;
use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Form\ImageCropTypeExtension;
use Nowo\UiKitBundle\Form\ImageInput;
use Nowo\UiKitBundle\Form\ImageInputTypeExtension;
use Nowo\UiKitBundle\Tests\Unit\PageContract\PageContractTwig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;

/**
 * 2.47: the image input box (FileType option `nowo_ui_image_input`, form/nowo_ui_image_input.html.twig, partial `image_input`).
 */
final class ImageInputTest extends TestCase
{
    private const THEME = "{% form_theme form '".NowoUiKitExtension::IMAGE_INPUT_FORM_THEME."' %}";

    public function testNormalize(): void
    {
        self::assertSame(['current_url' => null, 'default_url' => null, 'remove_field' => null, 'backdrop' => 'checkerboard', 'labels' => []], ImageInput::normalize([]));
        $closure = static fn (): string => '/x.png';
        $config = ImageInput::normalize(['current_url' => $closure, 'default_url' => '', 'remove_field' => 'removeLogo', 'backdrop' => 'dark', 'labels' => ['undo' => 'Back']]);
        self::assertSame($closure, $config['current_url']);
        self::assertNull($config['default_url']);
        self::assertSame('data:image/png;base64,iVBORw0KGgo=', ImageInput::url('data:image/png;base64,iVBORw0KGgo='));
        self::assertSame('https://cdn.example/a.png?x=1&y=2', ImageInput::url('https://cdn.example/a.png?x=1&y=2'));
        self::assertSame('media/a.png', ImageInput::url('media/a.png'));
    }

    /**
     * @return iterable<string, array{array<mixed>, string}>
     */
    public static function invalid(): iterable
    {
        yield 'unknown' => [['size' => 1], 'Unknown image input option(s) "size"'];
        yield 'javascript url' => [['current_url' => 'javascript:alert(1)'], '"javascript" scheme'];
        yield 'svg data' => [['default_url' => 'data:text/html;base64,PHN2Zz4='], '"data" scheme'];
        yield 'whitespace' => [['default_url' => '/a b.png'], 'without whitespace'];
        yield 'not a string' => [['current_url' => 3], 'without whitespace'];
        yield 'remove field' => [['remove_field' => 'a b'], 'remove_field'];
        yield 'backdrop' => [['backdrop' => 'blue'], 'backdrop'];
        yield 'labels' => [['labels' => ['nope' => 'x']], 'labels'];
        yield 'label type' => [['labels' => ['undo' => 1]], 'labels'];
    }

    /**
     * @param array<mixed> $options
     */
    #[DataProvider('invalid')]
    public function testInvalidOptions(array $options, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);
        ImageInput::normalize($options);
    }

    public function testWithoutTheOptionAFileTypeRendersAsBefore(): void
    {
        $plain = Forms::createFormFactoryBuilder()->getFormFactory();
        foreach (['none', 'bootstrap5'] as $framework) {
            $template = ['t' => self::THEME.'{{ form_row(form.logo) }}'];
            $before = PageContractTwig::create($template, [], $framework)->render('t', ['form' => $this->form($plain)->createView()]);
            $after = PageContractTwig::create($template, [], $framework)->render('t', ['form' => $this->form($this->factory())->createView()]);
            self::assertSame($before, $after, $framework);
            self::assertStringNotContainsString('nowo-ui-image-input', $after);
        }
        self::assertSame([FileType::class], [...ImageInputTypeExtension::getExtendedTypes()]);
    }

    public function testStoredImageWithDefaultAndRemoveField(): void
    {
        $form = $this->form($this->factory(), [
            'current_url' => 'https://cdn.example/logo.png?v=2&t=1',
            'default_url' => '/img/default-logo.svg',
            'remove_field' => 'removeLogo',
        ], ['required' => true, 'label' => 'Company logo']);
        $html = $this->render($form->createView(), '{{ form_row(form.logo) }}{{ form_rest(form) }}');

        self::assertMatchesRegularExpression('#<div class="nowo-ui-image-input nowo-ui-image-input--checkerboard" data-nowo-ui-image-input data-nowo-ui-image-input-state="stored" data-nowo-ui-image-input-labels="\{&quot;replace&quot;:&quot;Replace image&quot;[^"]*" data-nowo-ui-image-input-current="https://cdn.example/logo.png\?v=2&amp;t=1" data-nowo-ui-image-input-default="/img/default-logo.svg">#', $html);
        self::assertStringContainsString('<img id="form_logo_preview" class="nowo-ui-image-input__preview" data-nowo-ui-image-input-preview src="https://cdn.example/logo.png?v=2&amp;t=1" alt="Company logo" decoding="async">', $html);
        self::assertMatchesRegularExpression('#<span class="nowo-ui-image-input__badge" data-nowo-ui-image-input-badge hidden>Default</span>#', $html);
        self::assertMatchesRegularExpression('#<input type="file" id="form_logo" name="form\[logo\]" class="nowo-ui-input nowo-ui-image-input__file" data-nowo-ui-image-input-file accept="image/\*">#', $html, 'no required while an image is stored');
        self::assertMatchesRegularExpression('#<button type="button" class="nowo-ui-btn nowo-ui-btn--secondary nowo-ui-btn--sm nowo-ui-image-input__button" data-nowo-ui-image-input-replace hidden>Replace image</button>#', $html);
        self::assertStringContainsString('data-nowo-ui-image-input-remove hidden>Remove image</button>', $html);
        self::assertStringContainsString('data-nowo-ui-image-input-undo hidden>Undo</button>', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-image-input__remove-field" data-nowo-ui-image-input-remove-field>.*<input type="checkbox" id="form_removeLogo" name="form\[removeLogo\]"#s', $html);
        self::assertSame(1, substr_count($html, 'name="form[removeLogo]"'), 'the remove row is printed once, inside the box');
        self::assertStringContainsString('<p class="nowo-ui-image-input__status" data-nowo-ui-image-input-status role="status" aria-live="polite"></p>', $html);
        self::assertSame(0, preg_match('/\son[a-z]+=|\sstyle=/i', $html), 'CSP: no inline handler or style');
    }

    public function testDefaultOnlyNothingStoredNoRemoveRow(): void
    {
        $form = $this->form($this->factory(), ['default_url' => '/img/default.svg', 'remove_field' => 'removeLogo', 'backdrop' => 'dark'], ['required' => true]);
        $html = $this->render($form->createView(), '{{ form_row(form.logo) }}{{ form_rest(form) }}');

        self::assertStringContainsString('nowo-ui-image-input--dark" data-nowo-ui-image-input data-nowo-ui-image-input-state="default"', $html);
        self::assertStringContainsString('src="/img/default.svg" alt="Logo"', $html, 'alt from the humanized name');
        self::assertMatchesRegularExpression('#data-nowo-ui-image-input-badge>Default</span>#', $html);
        self::assertStringContainsString('data-nowo-ui-image-input-file required accept="image/*"', $html);
        self::assertStringContainsString('data-nowo-ui-image-input-replace hidden>Choose image</button>', $html);
        self::assertStringNotContainsString('data-nowo-ui-image-input-remove', $html);
        self::assertStringNotContainsString('removeLogo', $html, 'nothing stored: the remove field is marked rendered, never printed');
    }

    public function testEmptyBoxClosureUrlTranslatedLabelsAndLabelOptions(): void
    {
        $factory = $this->factory($this->translator('es'));
        $form = $factory->createNamedBuilder('profile', FormType::class, ['avatar' => '/u/7.webp'])
            ->add('picture', FileType::class, [
                'mapped' => false,
                'label' => 'profile.picture',
                'translation_domain' => 'messages',
                ImageInputTypeExtension::OPTION => ['current_url' => static fn (FormInterface $field): ?string => $field->getParent()?->getData()['avatar'] ?? null],
            ])
            ->add('empty', FileType::class, ['label' => false, 'disabled' => true, ImageInputTypeExtension::OPTION => true])
            ->add('raw', FileType::class, ['label' => 'Raw <b>', 'translation_domain' => false, 'attr' => ['class' => 'host', 'data-x' => true, 'data-off' => false], 'help' => 'PNG or WebP', ImageInputTypeExtension::OPTION => ['labels' => ['choose' => 'Pick one']]])
            ->getForm();
        $twig = PageContractTwig::create(['t' => self::THEME.'{{ form_widget(form.picture) }}|{{ form_widget(form.empty) }}|{{ form_widget(form.raw) }}'], [], 'none', locale: 'es', messages: ['messages' => ['profile.picture' => 'Foto de perfil']]);
        $html = $twig->render('t', ['form' => $form->createView()]);
        [$picture, $empty, $raw] = explode('|', $html);

        self::assertStringContainsString('data-nowo-ui-image-input-current="/u/7.webp"', $picture);
        self::assertStringContainsString('alt="Foto de perfil"', $picture);
        self::assertStringContainsString('&quot;replace&quot;:&quot;Cambiar imagen&quot;', $picture);
        self::assertStringContainsString('data-nowo-ui-image-input-replace hidden>Cambiar imagen</button>', $picture);

        self::assertStringContainsString('data-nowo-ui-image-input-state="empty"', $empty);
        self::assertStringContainsString('data-nowo-ui-image-input-preview hidden alt=""', $empty);
        self::assertStringContainsString('data-nowo-ui-image-input-empty>Sin imagen</span>', $empty);
        self::assertStringContainsString(' disabled', $empty);
        self::assertStringContainsString('data-nowo-ui-image-input-disabled', $empty);

        self::assertStringContainsString('alt="Raw &lt;b&gt;"', $raw);
        self::assertStringContainsString('class="nowo-ui-input nowo-ui-image-input__file host"', $raw);
        self::assertStringContainsString(' data-x accept="image/*"', $raw);
        self::assertStringNotContainsString('data-off', $raw);
        self::assertStringContainsString('aria-describedby="profile_raw_help"', $raw);
        self::assertStringContainsString('>Pick one</button>', $raw);
    }

    public function testCropPreviewTargetsTheBoxAndErrorsMarkTheInput(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addTypeExtension(new ImageCropTypeExtension())->addTypeExtension(new ImageInputTypeExtension())->getFormFactory();
        $form = $factory->createNamedBuilder('form')
            ->add('avatar', FileType::class, [ImageCropTypeExtension::OPTION => ['shape' => 'circle'], ImageInputTypeExtension::OPTION => true])
            ->add('own', FileType::class, [ImageCropTypeExtension::OPTION => ['preview' => 'mine'], ImageInputTypeExtension::OPTION => true])
            ->getForm();
        $form->submit([]);
        $form->get('avatar')->addError(new FormError('Too big'));
        $html = $this->render($form->createView(), '{{ form_widget(form.avatar) }}{{ form_widget(form.own) }}');

        self::assertStringContainsString('data-nowo-ui-image-crop-preview="form_avatar_preview"', $html);
        self::assertStringContainsString('data-nowo-ui-image-crop-preview="mine"', $html, 'a preview the field set wins');
        self::assertStringContainsString('class="nowo-ui-input nowo-ui-image-input__file nowo-ui-input--invalid', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
    }

    public function testFieldOptionErrors(): void
    {
        $factory = $this->factory();
        try {
            $factory->createNamed('a', FileType::class, null, ['multiple' => true, ImageInputTypeExtension::OPTION => true]);
            self::fail('multiple');
        } catch (InvalidOptionsException $e) {
            self::assertStringContainsString('cannot be used with "multiple"', $e->getMessage());
        }
        try {
            $factory->createNamed('a', FileType::class, null, [ImageInputTypeExtension::OPTION => ['backdrop' => 'x']]);
            self::fail('backdrop');
        } catch (InvalidOptionsException $e) {
            self::assertStringContainsString('"nowo_ui_image_input": The image input backdrop', $e->getMessage());
        }
        self::assertArrayNotHasKey(ImageInputTypeExtension::OPTION, $factory->createNamed('a', FileType::class)->createView()->vars);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('the remove_field "missing" is not a field of the parent form of "logo"');
        $this->form($factory, ['remove_field' => 'missing'])->createView();
    }

    public function testRemoveFieldOnARootFieldFails(): void
    {
        $this->expectException(LogicException::class);
        $this->factory()->createNamed('logo', FileType::class, null, [ImageInputTypeExtension::OPTION => ['remove_field' => 'removeLogo']])->createView();
    }

    public function testNoJsSubmitWithTheRemoveCheckbox(): void
    {
        $factory = Forms::createFormFactoryBuilder()->addExtension(new HttpFoundationExtension())->addTypeExtension(new ImageInputTypeExtension())->getFormFactory();
        $form = $this->form($factory, ['current_url' => '/logo.png', 'remove_field' => 'removeLogo']);
        $file = new UploadedFile(__FILE__, 'logo.png', 'image/png', null, true);
        $form->submit(['logo' => $file, 'removeLogo' => '1']);

        self::assertTrue($form->isSynchronized());
        self::assertSame($file, $form->get('logo')->getData());
        self::assertTrue($form->get('removeLogo')->getData());
    }

    public function testEveryLocaleTranslatesEveryLabel(): void
    {
        foreach (glob(\dirname(__DIR__, 3).'/src/Resources/translations/NowoUiKitBundle.*.yaml') ?: [] as $file) {
            $messages = Yaml::parseFile($file);
            self::assertIsArray($messages);
            foreach (ImageInput::LABELS as $key) {
                self::assertIsString($messages['image_input.'.$key] ?? null, basename($file).' image_input.'.$key);
            }
            self::assertStringContainsString('%name%', $messages['image_input.selected'], basename($file));
        }
        self::assertSame(ImageInput::LABELS, array_keys(ImageInputTypeExtension::ENGLISH));
    }

    public function testRegisteredWithSymfonyFormAndTheTheme(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);
        $definition = $container->getDefinition('nowo_ui_kit.form.image_input_extension');
        self::assertSame(ImageInputTypeExtension::class, $definition->getClass());
        self::assertTrue($definition->hasTag('form.type_extension'));

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
        self::assertContains(NowoUiKitExtension::IMAGE_INPUT_FORM_THEME, $prepend->getExtensionConfig('twig')[0]['form_themes'] ?? []);
        $layout = (string) file_get_contents(\dirname(__DIR__, 3).'/src/Resources/views/form/nowo_ui_layout.html.twig');
        self::assertStringContainsString("{% use '".NowoUiKitExtension::IMAGE_INPUT_FORM_THEME."' %}", $layout);
    }

    /**
     * @param array<string, mixed>|true $config
     * @param array<string, mixed>      $options
     */
    private function form(FormFactoryInterface $factory, array|bool|null $config = null, array $options = []): FormInterface
    {
        $field = null === $config ? $options : $options + [ImageInputTypeExtension::OPTION => $config];

        return $factory->createNamedBuilder('form')
            ->add('logo', FileType::class, $field)
            ->add('removeLogo', CheckboxType::class, ['required' => false, 'mapped' => false])
            ->getForm();
    }

    private function render(FormView $view, string $body): string
    {
        return PageContractTwig::create(['t' => self::THEME.$body], [], 'none')->render('t', ['form' => $view]);
    }

    private function factory(?Translator $translator = null): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addTypeExtension(new ImageInputTypeExtension($translator))->getFormFactory();
    }

    private function translator(string $locale): Translator
    {
        $messages = Yaml::parseFile(\dirname(__DIR__, 3).'/src/Resources/translations/NowoUiKitBundle.'.$locale.'.yaml');
        self::assertIsArray($messages);
        $translator = new Translator($locale);
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', $messages, $locale, 'NowoUiKitBundle');

        return $translator;
    }
}
