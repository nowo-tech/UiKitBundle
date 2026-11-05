<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\DependencyInjection;

use Nowo\UiKitBundle\DependencyInjection\Configuration;
use Nowo\UiKitBundle\Theme\ListFiltersOptions;
use Nowo\UiKitBundle\Theme\Theme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * 2.30 `nowo_ui_kit.theme.pages.list.filters` config tree (host options of the list scaffold filters).
 */
final class ListFiltersConfigurationTest extends TestCase
{
    public function testUnsetKeepsThe229Defaults(): void
    {
        $config = $this->process(['theme' => ['pages' => ['list' => ['header_actions_in_filters' => true]]]]);
        self::assertArrayNotHasKey('filters', $config['theme']['pages']['list']);
        $options = Theme::fromConfig('bootstrap5', $config['theme'])->listFilters();
        self::assertEquals(new ListFiltersOptions(), $options);
        self::assertNull($options->display);
        self::assertSame('auto-fit', $options->grid);
        self::assertFalse($options->foldEnabled);
    }

    public function testDefaultsOfAnEmptyFiltersMap(): void
    {
        $config = $this->process(['theme' => ['pages' => ['list' => ['filters' => []]]]]);

        self::assertSame([
            'display' => null,
            'submit_icon' => null,
            'clear_icon' => null,
            'submit_class' => null,
            'clear_class' => null,
            'grid' => 'auto-fit',
            'fold' => [
                'enabled' => false,
                'cookie' => 'nowo_ui_filters_collapsed',
                'cookie_path' => '/',
                'icons' => ['expanded' => 'chevron_up', 'collapsed' => 'chevron_down'],
                'testids' => false,
                'morph' => false,
            ],
        ], $config['theme']['pages']['list']['filters']);
        self::assertEquals(new ListFiltersOptions(), Theme::fromConfig('bootstrap5', $config['theme'])->listFilters());
    }

    public function testBeaconLikeConfig(): void
    {
        $config = $this->process(['theme' => ['pages' => ['list' => ['filters' => [
            'display' => 'icon',
            'submit_icon' => 'tabler:filter',
            'clear_icon' => 'tabler:filter-off',
            'submit_class' => 'btn-primary btn-icon issue-filters__submit',
            'clear_class' => 'btn-ghost btn-icon issue-filters__clear',
            'grid' => 'repeat(auto-fill, minmax(min(100%, 13rem), 1fr))',
            'fold' => ['enabled' => true, 'cookie' => 'beacon_filters_collapsed', 'cookie_path' => '/app', 'icons' => ['expanded' => 'tabler:chevron-up'], 'testids' => true, 'morph' => true],
        ]]]]]);
        $options = Theme::fromConfig('bootstrap5', $config['theme'])->listFilters();

        self::assertSame('icon', $options->display);
        self::assertSame('tabler:filter', $options->submitIcon);
        self::assertSame('tabler:filter-off', $options->clearIcon);
        self::assertSame('btn-primary btn-icon issue-filters__submit', $options->submitClass);
        self::assertSame('btn-ghost btn-icon issue-filters__clear', $options->clearClass);
        self::assertSame('repeat(auto-fill, minmax(min(100%, 13rem), 1fr))', $options->gridColumns());
        self::assertSame([
            'fold_cookie' => 'beacon_filters_collapsed',
            'fold_cookie_path' => '/app',
            'icons' => ['expanded' => 'tabler:chevron-up', 'collapsed' => 'chevron_down'],
            'testids' => true,
            'morph' => true,
        ], $options->foldVariables());
        self::assertTrue($options->foldEnabled);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function invalidConfigs(): iterable
    {
        yield 'filters on a non-list page' => [['theme' => ['pages' => ['show' => ['filters' => []]]]], 'Invalid nowo_ui_kit.theme.pages.show.filters: only the list page type takes it.'];
        yield 'filters on a bundle page' => [['theme' => ['bundles' => ['NowoXBundle' => ['pages' => ['list' => ['filters' => []]]]]]], 'Unrecognized option "filters"'];
        yield 'unknown display' => [['theme' => ['pages' => ['list' => ['filters' => ['display' => 'big']]]]], 'The value "big" is not allowed'];
        yield 'unknown key' => [['theme' => ['pages' => ['list' => ['filters' => ['columns' => 3]]]]], 'Unrecognized option "columns"'];
        yield 'unsafe grid' => [['theme' => ['pages' => ['list' => ['filters' => ['grid' => 'repeat(2, 1fr); color: red']]]]], 'Invalid nowo_ui_kit.theme.pages.list.filters: grid "repeat(2, 1fr); color: red" must be auto-fit, auto-fill or a grid-template-columns track list'];
        yield 'grid url()' => [['theme' => ['pages' => ['list' => ['filters' => ['grid' => 'url(x)']]]]], 'grid "url(x)" must be'];
        yield 'grid comment opener' => [['theme' => ['pages' => ['list' => ['filters' => ['grid' => '1fr /*']]]]], 'no /* or */'];
        yield 'token comment opener' => [['theme' => ['tokens' => ['filters-grid-columns' => '1fr /*']]], '"/*" and "*/" are not allowed'];
        yield 'unbalanced grid' => [['theme' => ['pages' => ['list' => ['filters' => ['grid' => 'repeat(2, 1fr']]]]], 'grid "repeat(2, 1fr" must be'];
        yield 'bad cookie' => [['theme' => ['pages' => ['list' => ['filters' => ['fold' => ['cookie' => 'a;b']]]]]], 'fold.cookie "a;b" must be 1-64 letters'];
        yield 'bad cookie path' => [['theme' => ['pages' => ['list' => ['filters' => ['fold' => ['cookie_path' => 'admin']]]]]], 'fold.cookie_path "admin" must be an absolute URL path'];
        yield 'bad icon' => [['theme' => ['pages' => ['list' => ['filters' => ['submit_icon' => 'a b']]]]], 'submit_icon "a b" must be an icon name'];
        yield 'bad fold icon' => [['theme' => ['pages' => ['list' => ['filters' => ['fold' => ['icons' => ['collapsed' => '<x>']]]]]]], 'fold.icons.collapsed "<x>" must be an icon name'];
        yield 'empty class' => [['theme' => ['pages' => ['list' => ['filters' => ['clear_class' => ' ']]]]], 'clear_class must be a non-empty class list'];
        yield 'control character class' => [['theme' => ['pages' => ['list' => ['filters' => ['submit_class' => "a\x00b"]]]]], 'submit_class must be a non-empty class list'];
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('invalidConfigs')]
    public function testRejectsInvalidConfig(array $input, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        $this->process($input);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private function process(array $config): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [$config]);
    }
}
