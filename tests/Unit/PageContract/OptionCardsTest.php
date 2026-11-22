<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use Nowo\UiKitBundle\Theme\ThemeSlots;
use Nowo\UiKitBundle\View\Label;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DomCrawler\Crawler;
use Twig\Error\RuntimeError;

/**
 * Option cards (2.64, partial `option_cards`): clickable radio / checkbox cards over native inputs. Markup assertions
 * stand in for an axe run: fieldset + legend, wrapping label, accessible name and description links, no inline style.
 */
final class OptionCardsTest extends TestCase
{
    private const TEMPLATE = '@NowoUiKitBundle/partials/_option_cards.html.twig';

    /**
     * @return list<array<string, mixed>>
     */
    private function plans(): array
    {
        return [
            ['value' => 'basic', 'title' => 'Basic', 'meta' => '$9', 'description' => 'For one person.', 'badges' => ['Popular', ['label' => 'New', 'tone' => 'success']], 'checked' => true, 'icon' => 'home'],
            ['value' => 'pro', 'title' => 'Pro', 'meta' => '$29', 'checked' => true],
            ['value' => 'team', 'title' => 'Team', 'description' => 'For a team.', 'disabled' => true, 'attr' => ['class' => 'extra', 'data-testid' => 'team'], 'input_attr' => ['data-model' => 'plan', 'class' => 'in', 'name' => 'hijack', 'id' => 'hijack']],
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

    public function testRadioBasicMarkup(): void
    {
        $html = $this->render(['name' => 'plan', 'legend' => 'Plan', 'options' => $this->plans(), 'columns' => 3, 'id' => 'plans']);
        $c = new Crawler($html);

        $set = $c->filter('fieldset#plans[data-nowo-ui-option-cards]');
        self::assertCount(1, $set);
        self::assertSame('nowo-ui-option-cards nowo-ui-option-cards--basic nowo-ui-option-cards--cols-3', $set->attr('class'));
        self::assertSame('Plan', trim($set->filter('legend.nowo-ui-option-cards__legend')->text()));
        self::assertStringNotContainsString('legend--sr', $html);

        $cards = $c->filter('.nowo-ui-option-cards__grid > label.nowo-ui-option-card');
        self::assertCount(3, $cards);
        // The whole card is a label around a native radio, linked by for/id.
        $first = $cards->eq(0);
        self::assertSame('plans-1', $first->attr('for'));
        $input = $first->filter('input[type="radio"]');
        self::assertSame(['plan', 'basic', 'plans-1', 'plans-1-title', 'plans-1-desc'], [$input->attr('name'), $input->attr('value'), $input->attr('id'), $input->attr('aria-labelledby'), $input->attr('aria-describedby')]);
        self::assertNotNull($input->attr('checked'));
        // The input comes first (top-left) and the title row carries title and meta.
        self::assertSame('input', $first->children()->first()->nodeName());
        self::assertSame('Basic', trim($first->filter('.nowo-ui-option-card__header > #plans-1-title')->text()));
        self::assertSame('$9', trim($first->filter('.nowo-ui-option-card__header > .nowo-ui-option-card__meta')->text()));
        self::assertSame('For one person.', trim($first->filter('#plans-1-desc.nowo-ui-option-card__description')->text()));
        self::assertSame(['Popular', 'New'], $first->filter('.nowo-ui-option-card__badges > span')->each(static fn (Crawler $b): string => trim($b->text())));
        self::assertStringContainsString('nowo-ui-badge--success', (string) $first->filter('.nowo-ui-option-card__badges > span')->eq(1)->attr('class'));
        // Variant basic draws no icon.
        self::assertCount(0, $c->filter('.nowo-ui-option-card__icon'));

        // Radio: only the first checked option counts. No description: no aria-describedby, no empty description span.
        $second = $cards->eq(1)->filter('input');
        self::assertNull($second->attr('checked'));
        self::assertNull($second->attr('aria-describedby'));
        self::assertCount(0, $cards->eq(1)->filter('.nowo-ui-option-card__description'));

        // Disabled, per-card attributes, reserved input attributes stay ours.
        $team = $cards->eq(2);
        self::assertSame(['nowo-ui-option-card extra', 'team'], [$team->attr('class'), $team->attr('data-testid')]);
        $teamInput = $team->filter('input');
        self::assertNotNull($teamInput->attr('disabled'));
        self::assertSame(['plan', 'plans-3', 'plan', 'nowo-ui-option-card__input in'], [$teamInput->attr('name'), $teamInput->attr('id'), $teamInput->attr('data-model'), $teamInput->attr('class')]);
    }

    public function testNoInlineStyleAndNoScriptInTheMarkup(): void
    {
        $html = $this->render(['name' => 'plan', 'legend' => 'Plan', 'options' => $this->plans(), 'variant' => 'icon']);

        self::assertStringNotContainsString('style=', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString(' on', str_replace(' one', '', $html));
    }

    public function testCheckboxIcon(): void
    {
        $options = [
            ['value' => 'a', 'title' => 'Alpha', 'description' => 'First', 'icon' => 'home', 'checked' => true],
            ['value' => 'b', 'title' => 'Beta', 'icon' => 'grid', 'checked' => true],
        ];
        $c = new Crawler($this->render(['name' => 'features[]', 'type' => 'checkbox', 'variant' => 'icon', 'options' => $options, 'columns' => 4]));

        self::assertSame('nowo-ui-option-cards nowo-ui-option-cards--icon nowo-ui-option-cards--cols-4', $c->filter('fieldset')->attr('class'));
        self::assertSame('nowo-ui-option-cards-features', $c->filter('fieldset')->attr('id'));
        $inputs = $c->filter('input[type="checkbox"]');
        self::assertSame(['features[]', 'features[]'], $inputs->each(static fn (Crawler $i): string => (string) $i->attr('name')));
        // Checkbox: every checked option stays checked.
        self::assertSame([true, true], $inputs->each(static fn (Crawler $i): bool => null !== $i->attr('checked')));
        $card = $c->filter('label.nowo-ui-option-card')->eq(0);
        // Icon first, the input centred at the bottom (last child of the label).
        self::assertSame(['span', 'input'], [$card->children()->first()->nodeName(), $card->children()->last()->nodeName()]);
        self::assertCount(1, $card->filter('.nowo-ui-option-card__body > .nowo-ui-option-card__icon[aria-hidden="true"] svg, .nowo-ui-option-card__body > .nowo-ui-option-card__icon[aria-hidden="true"] i'));
        self::assertSame(['Alpha'], $card->filter('.nowo-ui-option-card__title')->each(static fn (Crawler $t): string => trim($t->text())));
    }

    public function testIconIsSkippedWithoutAnIconSet(): void
    {
        $html = $this->render(['name' => 'x', 'variant' => 'icon', 'options' => [['value' => 'a', 'title' => 'A', 'icon' => 'home']]], 'none');

        self::assertStringNotContainsString('nowo-ui-option-card__icon', $html);
        self::assertStringNotContainsString('nowo-ui-icon--text', $html);
    }

    public function testDefaultsAndClamping(): void
    {
        $c = new Crawler($this->render(['name' => 'My Field.x', 'columns' => 9, 'options' => [['value' => 'a']]]));
        self::assertSame('nowo-ui-option-cards nowo-ui-option-cards--basic nowo-ui-option-cards--cols-4', $c->filter('fieldset')->attr('class'));
        self::assertSame('nowo-ui-option-cards-my-field-x', $c->filter('fieldset')->attr('id'));
        self::assertSame('a', trim($c->filter('.nowo-ui-option-card__title')->text()), 'the value is the title when none is given');
        self::assertCount(0, $c->filter('legend'));

        $low = new Crawler($this->render(['name' => 'x', 'columns' => 0, 'type' => 'nope', 'variant' => 'nope', 'options' => []]));
        self::assertSame('nowo-ui-option-cards nowo-ui-option-cards--basic nowo-ui-option-cards--cols-1', $low->filter('fieldset')->attr('class'));
        self::assertSame('nowo-ui-option-cards-x', $low->filter('fieldset')->attr('id'));
        self::assertCount(0, $low->filter('label'));

        $none = new Crawler($this->render([]));
        self::assertSame('nowo-ui-option-cards-group', $none->filter('fieldset')->attr('id'));
        self::assertSame('2', substr((string) $none->filter('fieldset')->attr('class'), -1));
    }

    public function testScreenReaderLegendAndFieldsetAttributes(): void
    {
        $c = new Crawler($this->render(['name' => 'x', 'legend' => new Label('option_cards_legend', 'Demo'), 'legend_sr_only' => true, 'attr' => ['class' => 'mine', 'data-x' => 'y', 'id' => 'ignored'], 'id' => 'g', 'options' => [['value' => 'a', 'title' => new Label('t', 'Demo')]]]));

        self::assertSame('nowo-ui-option-cards__legend nowo-ui-option-cards__legend--sr', $c->filter('legend')->attr('class'));
        self::assertSame(['g', 'nowo-ui-option-cards nowo-ui-option-cards--basic nowo-ui-option-cards--cols-2 mine', 'y'], [$c->filter('fieldset')->attr('id'), $c->filter('fieldset')->attr('class'), $c->filter('fieldset')->attr('data-x')]);
    }

    public function testHostContentRawAndTemplate(): void
    {
        $options = [
            ['value' => 'a', 'title' => 'A', 'content' => '<em class="extra">gpt-5</em> +2'],
            ['value' => 'b', 'title' => 'B', 'content_template' => 'extra', 'content_context' => ['who' => '<b>x</b>']],
        ];
        $twig = PageContractTwig::create(['extra' => '<i>{{ who }}</i>']);
        $c = new Crawler($twig->render(self::TEMPLATE, ['name' => 'p', 'options' => $options]));

        $content = $c->filter('.nowo-ui-option-card__content');
        self::assertCount(2, $content);
        self::assertCount(1, $content->eq(0)->filter('em.extra'));
        self::assertSame('<i>&lt;b&gt;x&lt;/b&gt;</i>', trim($content->eq(1)->html()));
    }

    public function testTextIsEscaped(): void
    {
        $html = $this->render(['name' => 'p', 'legend' => '<b>L</b>', 'options' => [['value' => '"><x', 'title' => '<script>t</script>', 'meta' => '<i>m</i>', 'description' => '<u>d</u>', 'badges' => ['<s>b</s>']]]]);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<b>L', $html);
        self::assertStringNotContainsString('<i>m', $html);
        self::assertStringNotContainsString('<u>d', $html);
        self::assertStringNotContainsString('<s>b', $html);
        self::assertStringContainsString('value="&quot;&gt;&lt;x"', $html);
    }

    public function testInlineStyleIsRefused(): void
    {
        $this->expectException(RuntimeError::class);
        $this->render(['name' => 'p', 'options' => [['value' => 'a', 'attr' => ['style' => 'color:red']]]]);
    }

    public function testSlotClassesFollowTheTheme(): void
    {
        $twig = PageContractTwig::create([], ['classes' => ['option_card' => 'my-card'], 'attributes' => ['option_cards' => ['data-theme-x' => '1']]]);
        $html = $twig->render(self::TEMPLATE, ['name' => 'p', 'options' => [['value' => 'a']]]);

        self::assertMatchesRegularExpression('/<label class="[^"]*my-card[^"]*" for=/', $html);
        self::assertStringContainsString('data-theme-x="1"', $html);
    }

    public function testStylesheetPaintsStatesWithoutJavascript(): void
    {
        $css = (string) file_get_contents(PageContractTwig::root().'/src/Resources/public/css/nowo-ui.unlayered.css');
        $start = (int) strpos($css, 'Option cards (2.64');
        $block = substr($css, $start);

        // Checked: primary 2px border and tint through :has(), no script.
        self::assertMatchesRegularExpression('/\.nowo-ui-option-card:has\(\.nowo-ui-option-card__input:checked\)\s*\{[^}]*border-color: var\(--nowo-ui-option-card-checked-border, var\(--nowo-ui-primary\)\);[^}]*color-mix\(in srgb, var\(--nowo-ui-primary\) 6%/', $block);
        self::assertStringContainsString('var(--nowo-ui-option-card-border-width, 2px) solid', $block);
        self::assertMatchesRegularExpression('/\.nowo-ui-option-card:hover\s*\{[^}]*--nowo-ui-option-card-hover-border/', $block);
        self::assertMatchesRegularExpression('/:has\(\.nowo-ui-option-card__input:focus-visible\)\s*\{\s*outline: 2px solid/', $block);
        self::assertMatchesRegularExpression('/:has\(\.nowo-ui-option-card__input:disabled\)\s*\{[^}]*opacity:[^}]*cursor: not-allowed;/', $block);
        // Responsive: one column on phones (the default), the grid widens from 40rem and 48rem.
        self::assertStringContainsString('grid-template-columns: minmax(0, 1fr);', $block);
        self::assertStringContainsString('@media (min-width: 40rem)', $block);
        self::assertStringContainsString('@media (min-width: 48rem)', $block);
        // Colour literals never appear: tokens only.
        self::assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b|rgba?\(/', $block);
        self::assertStringNotContainsString('!important', $block);
    }
}
