<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Form;

use Nowo\UiKitBundle\Appearance\Appearance;
use Nowo\UiKitBundle\Appearance\AppearancePreset;
use Nowo\UiKitBundle\Appearance\AppearancePresetRegistry;
use Nowo\UiKitBundle\Appearance\ConfigAppearanceProvider;
use Nowo\UiKitBundle\Form\AppearanceType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Validator\Validation;

final class AppearanceTypeTest extends TestCase
{
    public function testBuildsFieldsAndRoundTripsTheArrayShape(): void
    {
        [$factory, $registry] = $this->factory();
        $current = (new ConfigAppearanceProvider($registry, ['brand_name' => 'Acme']))->getAppearance();

        $form = $factory->create(AppearanceType::class, $current->toArray());
        self::assertSame(['brand_name', 'brand_eyebrow', 'theme_id', 'theme_id_dark', 'light', 'dark', 'corner_style', 'border_strength', 'footer_fixed'], array_keys($form->all()));
        self::assertSame(['accent', 'accent_deep', 'danger', 'warn', 'paper', 'ink', 'surface'], array_keys($form->get('light')->all()));

        $themeChoices = $form->get('theme_id')->getConfig()->getOption('choices');
        self::assertSame(['Host brand' => 'host_brand', 'appearance.preset.ocean' => 'ocean', 'appearance.preset.slate' => 'slate', 'appearance.preset.sandstone' => 'sandstone', 'appearance.preset.custom' => 'custom'], $themeChoices);
        self::assertContains('midnight', $form->get('theme_id_dark')->getConfig()->getOption('choices'));
        self::assertSame(['appearance.corner.sharp' => 'sharp', 'appearance.corner.soft' => 'soft', 'appearance.corner.rounded' => 'rounded'], $form->get('corner_style')->getConfig()->getOption('choices'));

        $submitted = $current->toArray();
        $submitted['brand_name'] = 'New brand';
        $submitted['light']['accent'] = '#123456';
        $submitted['corner_style'] = 'rounded';
        $submitted['footer_fixed'] = '1';
        $form->submit($submitted);
        self::assertTrue($form->isSynchronized());
        self::assertTrue($form->isValid(), (string) $form->getErrors(true));

        /** @var array<string, mixed> $data */
        $data = $form->getData();
        $updated = Appearance::fromArray($data, $current);
        self::assertSame('New brand', $updated->brandName);
        self::assertSame('#123456', $updated->light->accent);
        self::assertSame('rounded', $updated->cornerStyle);
        self::assertTrue($updated->footerFixed);
    }

    public function testRejectsInvalidColoursAndHonoursOptions(): void
    {
        [$factory, $registry] = $this->factory();
        $current = (new ConfigAppearanceProvider($registry))->getAppearance();

        $form = $factory->create(AppearanceType::class, $current->toArray());
        $submitted = $current->toArray();
        $submitted['dark']['ink'] = 'javascript:alert(1)';
        $form->submit($submitted);
        self::assertFalse($form->isValid());

        $slim = $factory->create(AppearanceType::class, null, ['with_brand' => false, 'with_colors' => false, 'with_footer' => false]);
        self::assertSame(['theme_id', 'theme_id_dark', 'corner_style', 'border_strength'], array_keys($slim->all()));
    }

    /**
     * @return array{FormFactoryInterface, AppearancePresetRegistry}
     */
    private function factory(): array
    {
        $registry = new AppearancePresetRegistry([AppearancePreset::fromArray('host_brand', ['mode' => 'light'])]);
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new PreloadedExtension([new AppearanceType($registry)], []))
            ->addExtension(new ValidatorExtension(Validation::createValidator()))
            ->getFormFactory();

        return [$factory, $registry];
    }
}
