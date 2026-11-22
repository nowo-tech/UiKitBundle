<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormView;
use Twig\Error\RuntimeError;

/**
 * Option cards (2.64, partial `option_cards`): clickable radio / checkbox cards over native inputs. Markup assertions
 * stand in for an axe run: fieldset + legend, wrapping label, accessible name and description links, no inline style.
 */
final class OptionCardsTest extends TestCase
{
    private const TEMPLATE = '@NowoUiKitBundle/partials/_option_cards.html.twig';

    /**
     * @param array<string, mixed> $options
     */
    private function field(array $options = [], mixed $data = 'basic'): FormView
    {
        return PageContractTwig::forms()->createNamedBuilder('order', FormType::class, ['plan' => $data])
            ->add('plan', ChoiceType::class, $options + ['expanded' => true, 'choices' => ['Basic' => 'basic', 'Pro' => 'pro', 'Team' => 'team'], 'label' => 'Plan'])
            ->getForm()->createView()['plan'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function meta(): array
    {
        return [
            'basic' => ['meta' => '$9', 'description' => 'For one person.', 'badges' => ['Popular', ['label' => 'New', 'tone' => 'success']], 'icon' => 'home'],
            'team' => ['description' => 'For a team.', 'attr' => ['class' => 'extra', 'data-testid' => 'team']],
        ];
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function render(array $vars, string $icons = 'bootstrap-icons'): string
    {
        $twig = PageContractTwig::create();
        $twig->addGlobal('nowo_ui_kit_icon_set', $icons);

        return $twig->render(self::TEMPLATE, $vars);
    }

    public function testItIsAThemePartialWithItsSlots(): void
    {
        self::assertSame('@NowoUiKitBundle/partials/_option_cards.html.twig', ThemeSlots::PARTIALS['option_cards']);
        self::assertFileExists(PageContractTwig::root().'/src/Resources/views/partials/_option_cards.html.twig');
        foreach (['option_cards', 'option_cards_legend', 'option_cards_grid', 'option_card', 'option_card_input', 'option_card_icon', 'option_card_header', 'option_card_title', 'option_card_meta', 'option_card_description', 'option_card_badges', 'option_card_content'] as $slot) {
            self::assertTrue(ThemeSlots::has($slot), $slot);
        }
        $twig = PageContractTwig::create(['t' => "{{ nowo_ui_partial('option_cards') }}"], ['partials' => ['option_cards' => 'host/_cards.html.twig']]);
        self::assertSame('host/_cards.html.twig', $twig->render('t'));
    }

    public function testRadioBasicMarkupComesFromTheForm(): void
    {
        $field = $this->field(['choice_attr' => ['Team' => ['disabled' => true, 'data-model' => 'plan']]]);
        $html = $this->render(['field' => $field, 'options_meta' => $this->meta(), 'columns' => 3]);
        $c = new Crawler($html);

        $set = $c->filter('fieldset#order_plan[data-nowo-ui-option-cards]');
        self::assertCount(1, $set);
        self::assertSame('nowo-ui-option-cards nowo-ui-option-cards--basic nowo-ui-option-cards--cols-3', $set->attr('class'));
        self::assertSame('Plan', trim($set->filter('legend.nowo-ui-option-cards__legend')->text()), 'the legend defaults to the field label');
        self::assertStringNotContainsString('legend--sr', $html);

        $cards = $c->filter('.nowo-ui-option-cards__grid > label.nowo-ui-option-card');
        self::assertCount(3, $cards);
        $first = $cards->eq(0);
        self::assertSame('order_plan_0', $first->attr('for'));
        $input = $first->filter('input[type="radio"]');
        // name, value and checked come from the form; the links are ours.
        self::assertSame(['order[plan]', 'basic', 'order_plan_0', 'order_plan_0_title', 'order_plan_0_desc', 'nowo-ui-option-card__input'], [$input->attr('name'), $input->attr('value'), $input->attr('id'), $input->attr('aria-labelledby'), $input->attr('aria-describedby'), $input->attr('class')]);
        self::assertNotNull($input->attr('checked'));
        self::assertSame('input', $first->children()->first()->nodeName());
        self::assertCount(0, $c->filter('label label'), 'one label per card: no label nested by a base theme');
        self::assertSame('Basic', trim($first->filter('.nowo-ui-option-card__header > #order_plan_0_title')->text()));
        self::assertSame('$9', trim($first->filter('.nowo-ui-option-card__header > .nowo-ui-option-card__meta')->text()));
        self::assertSame('For one person.', trim($first->filter('#order_plan_0_desc.nowo-ui-option-card__description')->text()));
        self::assertSame(['Popular', 'New'], $first->filter('.nowo-ui-option-card__badges > span')->each(static fn (Crawler $b): string => trim($b->text())));
        self::assertStringContainsString('nowo-ui-badge--success', (string) $first->filter('.nowo-ui-option-card__badges > span')->eq(1)->attr('class'));
        self::assertCount(0, $c->filter('.nowo-ui-option-card__icon'), 'the basic variant draws no icon');

        $second = $cards->eq(1)->filter('input');
        self::assertNull($second->attr('checked'));
        self::assertNull($second->attr('aria-describedby'));
        self::assertCount(0, $cards->eq(1)->filter('.nowo-ui-option-card__description'));

        $team = $cards->eq(2);
        self::assertSame(['nowo-ui-option-card extra', 'team'], [$team->attr('class'), $team->attr('data-testid')]);
        self::assertNotNull($team->filter('input')->attr('disabled'));
        self::assertSame('plan', $team->filter('input')->attr('data-model'), 'choice_attr stays on the control');
    }

    public function testNoRawControlsInlineStyleOrScriptInTheMarkup(): void
    {
        $html = $this->render(['field' => $this->field(), 'options_meta' => $this->meta(), 'variant' => 'icon']);

        self::assertStringNotContainsString('style=', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertDoesNotMatchRegularExpression('/\son[a-z]+=/', $html);
    }

    public function testMultipleIsCheckboxesAndTheIconVariantPutsTheControlLast(): void
    {
        $field = $this->field(['multiple' => true], ['basic', 'pro']);
        $c = new Crawler($this->render(['field' => $field, 'options_meta' => $this->meta(), 'variant' => 'icon', 'columns' => 4]));

        self::assertSame('nowo-ui-option-cards nowo-ui-option-cards--icon nowo-ui-option-cards--cols-4', $c->filter('fieldset')->attr('class'));
        $inputs = $c->filter('input[type="checkbox"]');
        self::assertSame(['order[plan][]', 'order[plan][]', 'order[plan][]'], $inputs->each(static fn (Crawler $i): string => (string) $i->attr('name')));
        self::assertSame([true, true, false], $inputs->each(static fn (Crawler $i): bool => null !== $i->attr('checked')));
        $card = $c->filter('label.nowo-ui-option-card')->eq(0);
        self::assertSame(['span', 'input'], [$card->children()->first()->nodeName(), $card->children()->last()->nodeName()]);
        self::assertCount(1, $card->filter('.nowo-ui-option-card__body > .nowo-ui-option-card__icon[aria-hidden="true"]'));
    }

    public function testIconIsSkippedWithoutAnIconSet(): void
    {
        $html = $this->render(['field' => $this->field(), 'options_meta' => $this->meta(), 'variant' => 'icon'], 'none');

        self::assertStringNotContainsString('nowo-ui-option-card__icon', $html);
        self::assertStringNotContainsString('nowo-ui-icon--text', $html);
    }

    public function testDefaultsAndClamping(): void
    {
        $c = new Crawler($this->render(['field' => $this->field(), 'columns' => 9]));
        self::assertSame('nowo-ui-option-cards nowo-ui-option-cards--basic nowo-ui-option-cards--cols-4', $c->filter('fieldset')->attr('class'));
        self::assertCount(3, $c->filter('.nowo-ui-option-card__title'));

        $low = new Crawler($this->render(['field' => $this->field(), 'columns' => 0, 'variant' => 'nope']));
        self::assertSame('nowo-ui-option-cards nowo-ui-option-cards--basic nowo-ui-option-cards--cols-1', $low->filter('fieldset')->attr('class'));

        $default = new Crawler($this->render(['field' => $this->field()]));
        self::assertSame('2', substr((string) $default->filter('fieldset')->attr('class'), -1));
    }

    public function testLegendOptions(): void
    {
        $sr = new Crawler($this->render(['field' => $this->field(), 'legend' => new Label('option_cards_legend', 'Demo'), 'legend_sr_only' => true, 'attr' => ['class' => 'mine', 'data-x' => 'y', 'id' => 'ignored']]));
        self::assertSame('nowo-ui-option-cards__legend nowo-ui-option-cards__legend--sr', $sr->filter('legend')->attr('class'));
        self::assertSame(['order_plan', 'nowo-ui-option-cards nowo-ui-option-cards--basic nowo-ui-option-cards--cols-2 mine', 'y'], [$sr->filter('fieldset')->attr('id'), $sr->filter('fieldset')->attr('class'), $sr->filter('fieldset')->attr('data-x')]);

        $none = new Crawler($this->render(['field' => $this->field(), 'legend' => false, 'attr' => ['aria-labelledby' => 'row-label']]));
        self::assertCount(0, $none->filter('legend'));
        self::assertSame('row-label', $none->filter('fieldset')->attr('aria-labelledby'));

        $unlabelled = new Crawler($this->render(['field' => $this->field(['label' => false])]));
        self::assertCount(0, $unlabelled->filter('legend'));
        $humanized = new Crawler($this->render(['field' => $this->field(['label' => ''])]));
        self::assertSame('Plan', trim($humanized->filter('legend')->text()));
        $raw = new Crawler($this->render(['field' => $this->field(['translation_domain' => false, 'label' => 'Raw'])]));
        self::assertSame('Raw', trim($raw->filter('legend')->text()));
    }

    public function testErrorsAndHelpFollowTheCardsUnlessTheRowPrintsThem(): void
    {
        $form = PageContractTwig::forms()->createNamedBuilder('order', FormType::class)->add('plan', ChoiceType::class, ['expanded' => true, 'choices' => ['Basic' => 'basic'], 'help' => 'Pick one'])->getForm();
        $form->get('plan')->addError(new FormError('Pick a plan'));
        $field = $form->createView()['plan'];

        $c = new Crawler($this->render(['field' => $field]));
        self::assertStringContainsString('Pick a plan', $c->filter('fieldset')->text());
        self::assertStringContainsString('Pick one', $c->filter('fieldset')->text());

        // A FormView renders once: build a second one for the bare variant.
        $form2 = PageContractTwig::forms()->createNamedBuilder('order', FormType::class)->add('plan', ChoiceType::class, ['expanded' => true, 'choices' => ['Basic' => 'basic'], 'help' => 'Pick one'])->getForm();
        $form2->get('plan')->addError(new FormError('Pick a plan'));
        $bare = new Crawler($this->render(['field' => $form2->createView()['plan'], 'errors' => false]));
        self::assertStringNotContainsString('Pick a plan', $bare->filter('fieldset')->text());
    }

    public function testChoiceLabelsAreTranslatedByTheForm(): void
    {
        $field = $this->field(['choice_translation_domain' => 'cards', 'choice_label' => static fn (string $choice, string $key, string $value): string => 'plan.'.$value]);
        $twig = PageContractTwig::create([], messages: ['cards' => ['plan.basic' => 'Basic plan']]);
        $c = new Crawler($twig->render(self::TEMPLATE, ['field' => $field]));

        self::assertSame('Basic plan', trim($c->filter('#order_plan_0_title')->text()));
        self::assertSame('plan.pro', trim($c->filter('#order_plan_1_title')->text()), 'a missing translation prints the key');
    }

    public function testHostContentRawAndTemplate(): void
    {
        $meta = [
            'basic' => ['content' => '<em class="extra">gpt-5</em> +2'],
            'pro' => ['content_template' => 'extra', 'content_context' => ['who' => '<b>x</b>']],
        ];
        $twig = PageContractTwig::create(['extra' => '<i>{{ who }}</i>']);
        $c = new Crawler($twig->render(self::TEMPLATE, ['field' => $this->field(), 'options_meta' => $meta]));

        $content = $c->filter('.nowo-ui-option-card__content');
        self::assertCount(2, $content);
        self::assertCount(1, $content->eq(0)->filter('em.extra'));
        self::assertSame('<i>&lt;b&gt;x&lt;/b&gt;</i>', trim($content->eq(1)->html()));
    }

    public function testTextIsEscaped(): void
    {
        $field = $this->field(['choices' => ['<script>t</script>' => '"><x']]);
        $html = $this->render(['field' => $field, 'legend' => '<b>L</b>', 'options_meta' => ['"><x' => ['meta' => '<i>m</i>', 'description' => '<u>d</u>', 'badges' => ['<s>b</s>']]]]);

        foreach (['<script>', '<b>L', '<i>m', '<u>d', '<s>b'] as $raw) {
            self::assertStringNotContainsString($raw, $html);
        }
        self::assertStringContainsString('&lt;i&gt;m&lt;/i&gt;', $html);
    }

    public function testInlineStyleIsRefused(): void
    {
        $this->expectException(RuntimeError::class);
        $this->render(['field' => $this->field(), 'options_meta' => ['basic' => ['attr' => ['style' => 'color:red']]]]);
    }

    public function testSlotClassesFollowTheTheme(): void
    {
        $twig = PageContractTwig::create([], ['classes' => ['option_card' => 'my-card', 'option_card_input' => 'my-input'], 'attributes' => ['option_cards' => ['data-theme-x' => '1']]]);
        $html = $twig->render(self::TEMPLATE, ['field' => $this->field()]);

        self::assertMatchesRegularExpression('/<label class="[^"]*my-card[^"]*" for=/', $html);
        self::assertStringContainsString('my-input', $html);
        self::assertStringContainsString('data-theme-x="1"', $html);
    }

    public function testStylesheetPaintsStatesWithoutJavascript(): void
    {
        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.unlayered.css');
        $block = substr($css, (int) strpos($css, 'Option cards (2.64'));

        self::assertMatchesRegularExpression('/\.nowo-ui-option-card:has\(\.nowo-ui-option-card__input:checked\)\s*\{[^}]*border-color: var\(--nowo-ui-option-card-checked-border, var\(--nowo-ui-primary\)\);[^}]*color-mix\(in srgb, var\(--nowo-ui-primary\) 6%/', $block);
        self::assertStringContainsString('var(--nowo-ui-option-card-border-width, 2px) solid', $block);
        self::assertMatchesRegularExpression('/\.nowo-ui-option-card:hover\s*\{[^}]*--nowo-ui-option-card-hover-border/', $block);
        self::assertMatchesRegularExpression('/:has\(\.nowo-ui-option-card__input:focus-visible\)\s*\{\s*outline: 2px solid/', $block);
        self::assertMatchesRegularExpression('/:has\(\.nowo-ui-option-card__input:disabled\)\s*\{[^}]*opacity:[^}]*cursor: not-allowed;/', $block);
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $block);
        self::assertStringContainsString('@media (min-width: 40rem)', $block);
        self::assertStringContainsString('@media (min-width: 48rem)', $block);
        self::assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b|rgba?\(/', $block);
        self::assertStringNotContainsString('!important', $block);
    }
}
