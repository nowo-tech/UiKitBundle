<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

use Nowo\UiKitBundle\View\Label;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the {@see ListingCustomizerView} of a listing page from its definition ({@see ListingRegistry}) and the
 * viewer's stored preferences ({@see ListingPreferenceStoreInterface}, a cookie by default).
 *
 * Typical controller:
 *
 *     ['view' => $listing, 'filteredRequest' => $request] = $builder->forRequest(InvoiceListing::KEY, $request);
 *     $form = $this->createForm(InvoiceFilterType::class)->handleRequest($request); // hidden filters are gone
 */
final readonly class ListingCustomizerViewBuilder
{
    public function __construct(
        private ListingRegistry $registry,
        private ListingPreferenceStoreInterface $store,
        private ?TranslatorInterface $translator = null,
    ) {
    }

    /**
     * Builds the view and strips hidden filters (and a sort on a hidden column) from the request: returns the view
     * (with the ignored filters noted) and the request to filter with (`$request` itself when nothing changes).
     *
     * @return array{view: ListingCustomizerView, filteredRequest: Request}
     */
    public function forRequest(string $listingKey, Request $request, string $sortParam = 'sort', string $directionParam = 'dir'): array
    {
        $view = $this->build($listingKey, $request);

        return [
            'view' => $view->withIgnoredFilters($view->layout->ignoredFilters($request)),
            'filteredRequest' => $view->layout->filterRequest($request, $sortParam, $directionParam),
        ];
    }

    public function build(string $listingKey, Request $request): ListingCustomizerView
    {
        $definition = $this->registry->get($listingKey);
        $stored = $this->store->load($listingKey, $request);
        $columns = $stored['columns'] ?? null;
        $filters = $stored['filters'] ?? null;

        $layout = new ListingLayout(
            $definition->key(),
            ListingLayoutMerger::merge($definition->columns(), $columns),
            ListingLayoutMerger::merge($definition->filters(), $filters),
            null !== $columns || null !== $filters,
        );

        return new ListingCustomizerView(
            $definition->key(),
            $layout,
            [
                'columns' => array_map(fn (ListingFieldState $state): array => $this->clientField($state) + ['merged' => $state->merged], $layout->columns),
                'filters' => array_map($this->clientField(...), $layout->filters),
            ],
            $this->store->cookieName($listingKey),
            ListingTableMode::tryFrom($stored['mode'] ?? ''),
        );
    }

    /**
     * @return array{key: string, label: string, required: bool, visible: bool}
     */
    private function clientField(ListingFieldState $state): array
    {
        return [
            'key' => $state->field->key,
            'label' => $this->label($state->field->label),
            'required' => $state->field->required,
            'visible' => $state->visible,
        ];
    }

    private function label(Label|string $label): string
    {
        if (\is_string($label)) {
            return $label;
        }

        return null !== $this->translator ? $this->translator->trans($label->key, $label->params, $label->domain) : $label->key;
    }
}
