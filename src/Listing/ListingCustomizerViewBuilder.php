<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Listing;

use LogicException;
use Nowo\UiKitBundle\Listing\Http\ListingContextFactory;
use Nowo\UiKitBundle\View\Label;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the {@see ListingCustomizerView} of a listing page from its definition ({@see ListingRegistry}) and the
 * viewer's stored preferences ({@see ListingPreferenceReaderInterface}, a cookie by default).
 *
 * Typical controller:
 *
 *     ['view' => $listing, 'filteredRequest' => $request] = $builder->forRequest(InvoiceListing::KEY, $request);
 *     $form = $this->createForm(InvoiceFilterType::class)->handleRequest($request); // hidden filters are gone
 *
 * Store selection (2.49): a host {@see ListingPreferenceReaderInterface} (`$reader`, anything but the default cookie
 * store) wins; else `$store` when it implements the reader port (the default cookie store does); else `$store` is a
 * legacy {@see ListingPreferenceStoreInterface} that only takes a `Request` (deprecated, removed in 3.0).
 * A DDD host without a `Request` calls {@see self::buildForContext()}.
 */
final readonly class ListingCustomizerViewBuilder
{
    private ?ListingPreferenceReaderInterface $effectiveReader;

    /**
     * @param ListingPreferenceReaderInterface|ListingPreferenceStoreInterface $store  a legacy-only store is deprecated since 2.49
     * @param ListingPreferenceReaderInterface|null                            $reader the host reader port (2.49)
     * @param ListingViewerResolverInterface|null                              $viewer fills {@see ListingContext::$viewerId} (2.49)
     * @param bool                                                             $debug  `kernel.debug`: warn about a server-side reader without a viewer resolver (2.49)
     */
    public function __construct(
        private ListingRegistry $registry,
        private ListingPreferenceReaderInterface|ListingPreferenceStoreInterface $store,
        private ?TranslatorInterface $translator = null,
        ?ListingPreferenceReaderInterface $reader = null,
        private ?ListingViewerResolverInterface $viewer = null,
        private ?LoggerInterface $logger = null,
        private bool $debug = false,
    ) {
        if ($reader instanceof ListingPreferenceReaderInterface && !$reader instanceof CookieListingPreferenceStore) {
            $this->effectiveReader = $reader;
        } elseif ($store instanceof ListingPreferenceReaderInterface) {
            $this->effectiveReader = $store;
        } else {
            $this->effectiveReader = null;
            @trigger_error(\sprintf('Since nowo-tech/ui-kit-bundle 2.49: the listing store "%s" implements only %s, which takes the HttpFoundation Request; implement %s instead (removed in 3.0).', $store::class, ListingPreferenceStoreInterface::class, ListingPreferenceReaderInterface::class), \E_USER_DEPRECATED);
        }
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
        if ($this->effectiveReader instanceof ListingPreferenceReaderInterface) {
            $cookieName = $this->effectiveReader->cookieName($listingKey);
            if (null === $cookieName && !$this->viewer instanceof ListingViewerResolverInterface && $this->debug) {
                $this->logger?->warning('nowo_ui_kit listing "{listing}": the server-side preference reader {reader} runs without a {resolver}, so ListingContext::$viewerId is null; a per-viewer reader must return null then (fail closed).', [
                    'listing' => $listingKey,
                    'reader' => $this->effectiveReader::class,
                    'resolver' => ListingViewerResolverInterface::class,
                ]);
            }

            return $this->buildFromStored($listingKey, $this->effectiveReader->load($listingKey, ListingContextFactory::fromRequest($request, $this->viewer?->viewerId())), $cookieName);
        }
        $store = $this->store;
        \assert($store instanceof ListingPreferenceStoreInterface);

        return $this->buildFromStored($listingKey, $store->load($listingKey, $request), $store->cookieName($listingKey));
    }

    /**
     * 2.49, framework-free variant of {@see self::build()} for a host without an HttpFoundation request (DDD / hexagonal
     * presenters, tests, console previews). The host builds the {@see ListingContext}; `viewerId` is taken as given.
     *
     * @throws LogicException when the configured store is a legacy {@see ListingPreferenceStoreInterface} only
     */
    public function buildForContext(string $listingKey, ListingContext $context): ListingCustomizerView
    {
        if (!$this->effectiveReader instanceof ListingPreferenceReaderInterface) {
            throw new LogicException(\sprintf('The listing store "%s" implements only the deprecated %s (it needs a Request): implement %s to build listings from a ListingContext.', $this->store::class, ListingPreferenceStoreInterface::class, ListingPreferenceReaderInterface::class));
        }

        return $this->buildFromStored($listingKey, $this->effectiveReader->load($listingKey, $context), $this->effectiveReader->cookieName($listingKey));
    }

    /**
     * @param array{columns: array<mixed>|null, filters: array<mixed>|null, mode: string|null}|null $stored
     */
    private function buildFromStored(string $listingKey, ?array $stored, ?string $cookieName): ListingCustomizerView
    {
        $definition = $this->registry->get($listingKey);
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
            $cookieName,
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

        return $this->translator?->trans($label->key, $label->params, $label->domain) ?? $label->key;
    }
}
