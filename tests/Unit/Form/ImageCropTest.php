<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Form;

use InvalidArgumentException;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Form\ImageCrop;
use Nowo\UiKitBundle\Form\ImageCropTypeExtension;
use Nowo\UiKitBundle\Tests\Unit\PageContract\PageContractTwig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Yaml\Yaml;

/**
 * The image crop attribute contract (2.43): what the input carries for js/nowo-ui-image-crop.js, from the PHP helper, the FileType
 * option and the Twig function.
 */
final class ImageCropTest extends TestCase
{
    public function testDefaults(): void
    {
        self::assertSame([
            'data-nowo-ui-image-crop' => '',
            'data-nowo-ui-image-crop-shape' => 'square',
            'data-nowo-ui-image-crop-aspect' => '1',
            'data-nowo-ui-image-crop-max' => '1024',
            'data-nowo-ui-image-crop-format' => 'auto',
            'data-nowo-ui-image-crop-quality' => '0.92',
            'data-nowo-ui-image-crop-labels' => '{"title":"Crop the image","instructions":"Drag the image or use the arrow keys to move it. Zoom with the slider, the mouse wheel, a pinch, or the + and - keys. Enter applies, Escape cancels.","stage":"Image to crop","zoom":"Zoom","zoomStatus":"Zoom %percent%%","apply":"Apply","cancel":"Cancel","close":"Close","tooLarge":"This crop cannot be made smaller than %max%. Crop a smaller part of the image, or cancel.","optimizing":"Optimizing the image…"}',
        ], ImageCrop::attributes());
        $png = ImageCrop::attributes(['format' => 'png']);
        self::assertSame('png', $png['data-nowo-ui-image-crop-format']);
        self::assertArrayNotHasKey('data-nowo-ui-image-crop-quality', $png);
        self::assertArrayNotHasKey('data-nowo-ui-image-crop-max-bytes', $png);
    }

    public function testEveryOption(): void
    {
        $attributes = ImageCrop::attributes(['shape' => 'circle', 'aspect' => 'free', 'max' => 512, 'min' => 256, 'format' => 'webp', 'max_bytes' => 204_800, 'quality' => 0.8, 'bake_circle' => true, 'preview' => 'avatar-preview', 'labels' => ['apply' => 'Use']]);

        self::assertSame('circle', $attributes['data-nowo-ui-image-crop-shape']);
        // A circle is always 1:1.
        self::assertSame('1', $attributes['data-nowo-ui-image-crop-aspect']);
        self::assertSame('512', $attributes['data-nowo-ui-image-crop-max']);
        self::assertSame('256', $attributes['data-nowo-ui-image-crop-min']);
        self::assertSame('webp', $attributes['data-nowo-ui-image-crop-format']);
        self::assertSame('0.8', $attributes['data-nowo-ui-image-crop-quality']);
        self::assertSame('204800', $attributes['data-nowo-ui-image-crop-max-bytes']);
        self::assertSame('true', ImageCrop::attributes(['shape' => 'circle', 'format' => 'auto', 'bake_circle' => true])['data-nowo-ui-image-crop-bake-circle']);
        self::assertSame('true', $attributes['data-nowo-ui-image-crop-bake-circle']);
        self::assertSame('avatar-preview', $attributes['data-nowo-ui-image-crop-preview']);
        self::assertSame('Use', json_decode($attributes['data-nowo-ui-image-crop-labels'], true, 2, \JSON_THROW_ON_ERROR)['apply']);
        self::assertSame('1', ImageCrop::attributes(['format' => 'jpeg', 'quality' => 1])['data-nowo-ui-image-crop-quality']);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function aspects(): iterable
    {
        yield 'free' => ['free', 'free'];
        yield 'int' => [1, '1'];
        yield 'float' => [1.5, '1.5'];
        yield 'long float' => [16 / 9, '1.777778'];
        yield 'ratio' => ['16/9', '16/9'];
        yield 'colon' => [' 4:3 ', '4/3'];
        yield 'numeric string' => ['2', '2'];
    }

    #[DataProvider('aspects')]
    public function testAspect(mixed $aspect, string $expected): void
    {
        self::assertSame($expected, ImageCrop::aspect($aspect));
        self::assertSame($expected, ImageCrop::attributes(['aspect' => $aspect])['data-nowo-ui-image-crop-aspect']);
    }

    /**
     * @return iterable<string, array{array<mixed>}>
     */
    public static function invalid(): iterable
    {
        yield 'unknown key' => [['size' => 3]];
        yield 'shape' => [['shape' => 'star']];
        yield 'format' => [['format' => 'gif']];
        yield 'max too small' => [['max' => 8]];
        yield 'max too large' => [['max' => 9000]];
        yield 'max string' => [['max' => '512']];
        yield 'min zero' => [['min' => 0]];
        yield 'max_bytes zero' => [['max_bytes' => 0]];
        yield 'max_bytes string' => [['max_bytes' => '300000']];
        yield 'min string' => [['min' => '1']];
        yield 'quality' => [['quality' => 1.5]];
        yield 'quality string' => [['quality' => '0.5']];
        yield 'negative quality' => [['quality' => -0.1]];
        yield 'NAN quality' => [['quality' => \NAN]];
        yield 'bake not a bool' => [['shape' => 'circle', 'bake_circle' => 'yes']];
        yield 'bake a square' => [['bake_circle' => true]];
        yield 'bake a jpeg' => [['shape' => 'circle', 'format' => 'jpeg', 'bake_circle' => true]];
        yield 'preview' => [['preview' => '" onload="x']];
        yield 'preview not a string' => [['preview' => 3]];
        yield 'labels key' => [['labels' => ['other' => 'x']]];
        yield 'labels value' => [['labels' => ['apply' => 3]]];
        yield 'labels list' => [['labels' => 'Apply']];
        yield 'aspect zero' => [['aspect' => 0]];
        yield 'aspect word' => [['aspect' => 'wide']];
        yield 'aspect ratio zero' => [['aspect' => '0/9']];
        yield 'aspect string zero' => [['aspect' => '0']];
        yield 'aspect infinite' => [['aspect' => \INF]];
    }

    /**
     * @param array<mixed> $options
     */
    #[DataProvider('invalid')]
    public function testInvalidOptions(array $options): void
    {
        $this->expectException(InvalidArgumentException::class);
        ImageCrop::attributes($options);
    }

    public function testTranslatedLabels(): void
    {
        $labels = ImageCrop::labels(['cancel' => 'No'], $this->translator('es'));

        self::assertSame('Recortar la imagen', $labels['title']);
        self::assertSame('Zoom %percent%%', $labels['zoomStatus']);
        self::assertSame('No', $labels['cancel']);
        self::assertSame(['title', 'instructions', 'stage', 'zoom', 'zoomStatus', 'apply', 'cancel', 'close', 'tooLarge', 'optimizing'], array_keys($labels));
    }

    public function testEveryLocaleTranslatesEveryLabel(): void
    {
        foreach (glob(\dirname(__DIR__, 3).'/src/Resources/translations/NowoUiKitBundle.*.yaml') ?: [] as $file) {
            $messages = Yaml::parseFile($file);
            self::assertIsArray($messages);
            foreach (['title', 'instructions', 'stage', 'zoom', 'zoom_status', 'apply', 'cancel', 'close', 'too_large', 'optimizing'] as $key) {
                self::assertIsString($messages['image_crop.'.$key] ?? null, basename($file).' image_crop.'.$key);
            }
            self::assertStringContainsString('%percent%', $messages['image_crop.zoom_status'], basename($file));
            self::assertStringContainsString('%max%', $messages['image_crop.too_large'], basename($file));
        }
    }

    public function testTheFileTypeOption(): void
    {
        $view = $this->factory($this->translator('es'))->createNamed('avatar', FileType::class, null, [
            'attr' => ['accept' => 'image/png,image/webp', 'data-nowo-ui-image-crop-max' => '300'],
            ImageCropTypeExtension::OPTION => ['shape' => 'circle', 'max' => 512, 'format' => 'webp'],
        ])->createView();

        self::assertTrue($view->vars[ImageCropTypeExtension::OPTION]);
        self::assertSame('image/png,image/webp', $view->vars['attr']['accept']);
        // The field's own attr wins.
        self::assertSame('300', $view->vars['attr']['data-nowo-ui-image-crop-max']);
        self::assertSame('circle', $view->vars['attr']['data-nowo-ui-image-crop-shape']);
        self::assertSame('webp', $view->vars['attr']['data-nowo-ui-image-crop-format']);
        self::assertStringContainsString('"apply":"Aplicar"', $view->vars['attr']['data-nowo-ui-image-crop-labels']);

        $html = PageContractTwig::create(['t' => '{{ form_widget(field) }}'], [], 'none')->render('t', ['field' => $view]);
        self::assertMatchesRegularExpression('/<input type="file"[^>]* data-nowo-ui-image-crop=""/', $html);
        self::assertStringContainsString('data-nowo-ui-image-crop-shape="circle"', $html);
    }

    public function testTrueGivesTheDefaultsAndFalseNothing(): void
    {
        $factory = $this->factory();
        $on = $factory->createNamed('a', FileType::class, null, [ImageCropTypeExtension::OPTION => true])->createView();
        $off = $factory->createNamed('b', FileType::class)->createView();

        self::assertSame(ImageCrop::attributes(), $on->vars['attr']);
        self::assertArrayNotHasKey('data-nowo-ui-image-crop', $off->vars['attr']);
        self::assertArrayNotHasKey(ImageCropTypeExtension::OPTION, $off->vars);
        self::assertSame([FileType::class], [...ImageCropTypeExtension::getExtendedTypes()]);
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function invalidFieldOptions(): iterable
    {
        yield 'multiple' => [['multiple' => true, ImageCropTypeExtension::OPTION => true]];
        yield 'bad option' => [[ImageCropTypeExtension::OPTION => ['shape' => 'star']]];
        yield 'not an array' => [[ImageCropTypeExtension::OPTION => 'circle']];
    }

    /**
     * @param array<string, mixed> $options
     */
    #[DataProvider('invalidFieldOptions')]
    public function testTheFileTypeOptionRefusesBadValues(array $options): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->factory()->createNamed('avatar', FileType::class, null, $options);
    }

    public function testMultipleWithoutTheCropIsFine(): void
    {
        $view = $this->factory()->createNamed('files', FileType::class, null, ['multiple' => true])->createView();

        self::assertArrayNotHasKey('data-nowo-ui-image-crop', $view->vars['attr']);
    }

    public function testTheTwigFunction(): void
    {
        $twig = PageContractTwig::create(['t' => "<input type=\"file\" name=\"logo\"{{ nowo_ui_html_attrs(nowo_ui_image_crop_attrs({shape: 'square', aspect: '16/9', max: 800})) }}>"]);
        $html = $twig->render('t');

        self::assertStringContainsString('data-nowo-ui-image-crop="" data-nowo-ui-image-crop-shape="square" data-nowo-ui-image-crop-aspect="16/9" data-nowo-ui-image-crop-max="800" data-nowo-ui-image-crop-format="auto" data-nowo-ui-image-crop-quality="0.92" data-nowo-ui-image-crop-labels="{&quot;title&quot;', $html);
        self::assertStringNotContainsString('style=', $html);
    }

    public function testRegisteredWithSymfonyForm(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);

        $definition = $container->getDefinition('nowo_ui_kit.form.image_crop_extension');
        self::assertSame(ImageCropTypeExtension::class, $definition->getClass());
        self::assertTrue($definition->hasTag('form.type_extension'));
        self::assertSame('translator', (string) $definition->getArgument(0));
    }

    private function factory(?Translator $translator = null): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->addTypeExtension(new ImageCropTypeExtension($translator))->getFormFactory();
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
