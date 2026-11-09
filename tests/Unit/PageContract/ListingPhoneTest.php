<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\PageContract;

use InvalidArgumentException;
use Nowo\UiKitBundle\Listing\ListingDefinitionInterface;
use Nowo\UiKitBundle\Listing\ListingField;
use Nowo\UiKitBundle\Listing\ListingLayout;
use Nowo\UiKitBundle\Listing\ListingLayoutMerger;
use Nowo\UiKitBundle\View\Column;
use PHPUnit\Framework\TestCase;

/**
 * 2.42 (MUX-20 / MUX-22): ListingField::$phone — the place of a listing column in the phone card of a responsive table
 * with `phone_layout: 'cards'`, validated like Column::$phone, carried by the resolved layout and printed by
 * `listing_head` / `listing_cells` as data-nowo-ui-phone (only when set).
 */
final class ListingPhoneTest extends TestCase
{
    private const CELLS = <<<'TWIG'
        {% block cell_name %}{{ row.name }}{% endblock %}
        {% block cell_email %}{{ row.email }}{% endblock %}
        {% block cell_id %}{{ row.id }}{% endblock %}
        {% block cell_role %}{{ row.role }}{% endblock %}
        TWIG;

    public function testDefaultsToNullAndTakesTheColumnValues(): void
    {
        self::assertNull((new ListingField('a', 'A'))->phone);
        foreach (Column::PHONE as $phone) {
            self::assertSame($phone, (new ListingField('a', 'A', phone: $phone))->phone);
        }
    }

    public function testInvalidPhoneIsRefusedLikeColumn(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid ListingField phone "title" (primary|meta|hide).');
        new ListingField('a', 'A', phone: 'title');
    }

    public function testColumnSharesTheValidator(): void
    {
        Column::assertPhone(null);
        Column::assertPhone('meta');
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid Column phone "" (primary|meta|hide).');
        new Column('a', phone: '');
    }

    public function testResolvedLayoutCarriesPhoneWhateverTheStoredCustomization(): void
    {
        $fields = self::definition()->columns();
        // Reordered, one hidden, and a forged `phone` in the stored entry: only key / visible / merged are read.
        $stored = [
            ['key' => 'role', 'visible' => true, 'phone' => 'primary'],
            ['key' => 'email', 'visible' => false],
            ['key' => 'id', 'visible' => true],
            ['key' => 'name', 'visible' => true],
        ];
        $layout = new ListingLayout('users', ListingLayoutMerger::merge($fields, $stored), [], true);
        $phones = [];
        foreach ($layout->visibleColumns() as $column) {
            $phones[$column->key] = $column->phone;
        }
        self::assertSame(['role' => null, 'id' => 'hide', 'name' => 'primary'], $phones);
        self::assertNull(ListingLayoutMerger::normalize($fields, [['key' => 'name', 'visible' => true, 'merged' => 'primary']]));
        self::assertSame([['key' => 'name', 'visible' => true]], ListingLayoutMerger::normalize($fields, [['key' => 'name', 'visible' => true, 'phone' => 'hide']]));
    }

    public function testHeadAndCellsPrintThePhoneOnlyWhenSet(): void
    {
        $layout = new ListingLayout('users', ListingLayoutMerger::merge(self::definition()->columns(), null), []);
        $row = ['name' => 'Ada', 'email' => 'a@x', 'id' => 7, 'role' => 'admin'];
        $head = $this->render("<tr>{% include nowo_ui_partial('listing_head') with {listing_layout: layout, detail: false} only %}</tr>", ['layout' => $layout]);
        self::assertStringContainsString('data-column="name" data-columns="name" data-required="1" data-priority="0" data-nowo-ui-phone="primary">Name</th>', $head);
        self::assertStringContainsString('data-column="email" data-columns="email" data-priority="0" data-nowo-ui-phone="meta">Email</th>', $head);
        self::assertStringContainsString('data-column="id" data-columns="id" data-priority="0" data-nowo-ui-phone="hide">Id</th>', $head);
        self::assertStringContainsString('data-column="role" data-columns="role" data-priority="0">Role</th>', $head);

        $cells = $this->render("<tr>{% include nowo_ui_partial('listing_cells') with {listing_layout: layout, cells_template: 'cells.html.twig', detail: false} %}</tr>", ['layout' => $layout, 'row' => $row]);
        self::assertStringContainsString('<td class="nowo-ui-table__cell" data-column="name" data-priority="0" data-nowo-ui-phone="primary">Ada</td>', $cells);
        self::assertStringContainsString('<td class="nowo-ui-table__cell" data-column="email" data-priority="0" data-nowo-ui-phone="meta">a@x</td>', $cells);
        self::assertStringContainsString('<td class="nowo-ui-table__cell" data-column="id" data-priority="0" data-nowo-ui-phone="hide">7</td>', $cells);
        self::assertStringContainsString('<td class="nowo-ui-table__cell" data-column="role" data-priority="0">admin</td>', $cells);

        // A stacked group follows its lead column (the stacked column's own phone is not printed).
        $stacked = new ListingLayout('users', ListingLayoutMerger::merge(self::definition()->columns(), [['key' => 'role', 'visible' => true], ['key' => 'email', 'visible' => true, 'merged' => true]]), []);
        $html = $this->render("<tr>{% include nowo_ui_partial('listing_cells') with {listing_layout: layout, cells_template: 'cells.html.twig', detail: false} %}</tr>", ['layout' => $stacked, 'row' => $row]);
        self::assertStringContainsString('<td class="nowo-ui-table__cell nowo-ui-listing-cell--stacked" data-column="role" data-priority="0"><div', $html);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function render(string $template, array $context): string
    {
        return PageContractTwig::create(['page.html.twig' => $template, 'cells.html.twig' => self::CELLS])->render('page.html.twig', $context);
    }

    private static function definition(): ListingDefinitionInterface
    {
        return new class implements ListingDefinitionInterface {
            public function key(): string
            {
                return 'users';
            }

            public function columns(): array
            {
                return [
                    new ListingField('name', 'Name', required: true, phone: 'primary'),
                    new ListingField('email', 'Email', phone: 'meta'),
                    new ListingField('id', 'Id', phone: 'hide'),
                    new ListingField('role', 'Role'),
                ];
            }

            public function filters(): array
            {
                return [];
            }
        };
    }
}
