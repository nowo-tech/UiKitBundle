<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Listing;

use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Listing\CookieListingPreferenceStore;
use Nowo\UiKitBundle\Listing\ListingCustomizerViewBuilder;
use Nowo\UiKitBundle\Listing\ListingDefinitionInterface;
use Nowo\UiKitBundle\Listing\ListingPreferenceStoreInterface;
use Nowo\UiKitBundle\Listing\ListingRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

final class ListingDependencyInjectionTest extends TestCase
{
    public function testServicesAndAutoconfiguration(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);

        $registry = $container->getDefinition('nowo_ui_kit.listing.registry');
        $argument = $registry->getArgument(0);
        self::assertInstanceOf(TaggedIteratorArgument::class, $argument);
        self::assertSame(ListingDefinitionInterface::TAG, $argument->getTag());
        self::assertSame('nowo_ui_kit.listing_definition', ListingDefinitionInterface::TAG);
        self::assertSame('nowo_ui_kit.listing.registry', (string) $container->getAlias(ListingRegistry::class));
        self::assertSame('nowo_ui_kit.listing.cookie_store', (string) $container->getAlias(ListingPreferenceStoreInterface::class));
        self::assertSame('nowo_ui_kit.listing.cookie_store', (string) $container->getAlias(CookieListingPreferenceStore::class));
        self::assertSame('nowo_ui_kit.listing.view_builder', (string) $container->getAlias(ListingCustomizerViewBuilder::class));
        self::assertArrayHasKey(ListingDefinitionInterface::class, $container->getAutoconfiguredInstanceof());
        self::assertTrue($container->getAutoconfiguredInstanceof()[ListingDefinitionInterface::class]->hasTag(ListingDefinitionInterface::TAG));
    }

    public function testCompiledContainerResolvesTaggedDefinitions(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);
        $container->register(InvoiceListing::class, InvoiceListing::class)->setAutoconfigured(true);
        $container->getAlias(ListingCustomizerViewBuilder::class)->setPublic(true);
        $container->getAlias(ListingRegistry::class)->setPublic(true);
        $container->compile();

        $registry = $container->get(ListingRegistry::class);
        self::assertInstanceOf(ListingRegistry::class, $registry);
        self::assertSame([InvoiceListing::KEY], $registry->keys());

        $builder = $container->get(ListingCustomizerViewBuilder::class);
        self::assertInstanceOf(ListingCustomizerViewBuilder::class, $builder);
        self::assertSame('nowo_ui_listing_invoices_index', $builder->build(InvoiceListing::KEY, new Request())->cookieName);
    }

    public function testHostCanReplaceTheStore(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([[]], $container);
        $container->register('app.listing_store', ServerListingStore::class);
        $container->setAlias(ListingPreferenceStoreInterface::class, 'app.listing_store');
        $container->register(InvoiceListing::class, InvoiceListing::class)->setAutoconfigured(true);
        $container->getAlias(ListingCustomizerViewBuilder::class)->setPublic(true);
        $container->compile();

        $builder = $container->get(ListingCustomizerViewBuilder::class);
        self::assertInstanceOf(ListingCustomizerViewBuilder::class, $builder);
        self::assertNull($builder->build(InvoiceListing::KEY, new Request())->cookieName);
    }
}
