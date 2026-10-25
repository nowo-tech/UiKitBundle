<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

/**
 * What a listing page needs to render itself and its customizer: the effective {@see ListingLayout} (head, cells,
 * filter fields), the table mode, the state of the `listing-customizer` peer and the hidden filters the request
 * tried to use. Built by {@see ListingCustomizerViewBuilder}.
 */
final readonly class ListingCustomizerView
{
    /**
     * @param array{columns: list<array{key: string, label: string, required: bool, visible: bool, merged: bool}>, filters: list<array{key: string, label: string, required: bool, visible: bool}>} $fields         editor rows (labels translated)
     * @param string|null                                                                                                                                                                           $cookieName     cookie the browser writes (null: server-side store, pass a `save_url`)
     * @param list<ListingField>                                                                                                                                                                    $ignoredFilters hidden filters that had a value in the request
     */
    public function __construct(
        public string $listingKey,
        public ListingLayout $layout,
        public array $fields,
        public ?string $cookieName = null,
        public ?ListingTableMode $tableMode = null,
        public array $ignoredFilters = [],
    ) {
    }

    public function hasColumns(): bool
    {
        return [] !== $this->layout->columns;
    }

    public function hasFilters(): bool
    {
        return [] !== $this->layout->filters;
    }

    /**
     * Id of the customizer dialog (`data-nowo-ui-dialog-open` target for a host opener).
     */
    public function dialogId(): string
    {
        return 'nowo-ui-listing-'.preg_replace('/[^A-Za-z0-9_-]/', '-', $this->listingKey);
    }

    /**
     * Table mode the page renders: the viewer's choice, else '' (the responsive table then applies its default:
     * responsive on phones, scroll elsewhere).
     */
    public function tableModeValue(): string
    {
        return $this->tableMode->value ?? '';
    }

    /**
     * Options for `partials/_responsive_table.html.twig` that keep the table mode in the listing store:
     * `{% embed '@NowoUiKitBundle/partials/_responsive_table.html.twig' with listing.tableOptions|merge({…}) %}`.
     *
     * @return array{table_mode: string, save_cookie?: string}
     */
    public function tableOptions(): array
    {
        return ['table_mode' => $this->tableModeValue()] + (null !== $this->cookieName ? ['save_cookie' => $this->cookieName] : []);
    }

    /**
     * Client state of the `listing-customizer` Stimulus peer.
     *
     * @return array{listingKey: string, stored: bool, mode: string|null, columns: list<array{key: string, label: string, required: bool, visible: bool, merged: bool}>, filters: list<array{key: string, label: string, required: bool, visible: bool}>}
     */
    public function clientState(): array
    {
        return [
            'listingKey' => $this->listingKey,
            'stored' => $this->layout->stored,
            'mode' => $this->tableMode?->value,
            'columns' => $this->fields['columns'],
            'filters' => $this->fields['filters'],
        ];
    }

    /**
     * @param list<ListingField> $ignoredFilters
     */
    public function withIgnoredFilters(array $ignoredFilters): self
    {
        return new self($this->listingKey, $this->layout, $this->fields, $this->cookieName, $this->tableMode, $ignoredFilters);
    }

    /**
     * Copy rendering another layout (e.g. `$view->layout->withoutColumns([…])`); the editor keeps the viewer's
     * stored choices.
     */
    public function withLayout(ListingLayout $layout): self
    {
        return new self($this->listingKey, $layout, $this->fields, $this->cookieName, $this->tableMode, $this->ignoredFilters);
    }
}
