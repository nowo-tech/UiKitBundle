<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Unit\Dialog;

use InvalidArgumentException;
use LogicException;
use Nowo\UiKitBundle\DependencyInjection\Compiler\RemoteDialogPageResolverPass;
use Nowo\UiKitBundle\DependencyInjection\NowoUiKitExtension;
use Nowo\UiKitBundle\Dialog\RemoteDialogContent;
use Nowo\UiKitBundle\Dialog\RemoteDialogContentMap;
use Nowo\UiKitBundle\Dialog\RemoteDialogPageMappingCheck;
use Nowo\UiKitBundle\Dialog\RemoteDialogPageResolverInterface;
use Nowo\UiKitBundle\Dialog\RemoteDialogReopen;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use stdClass;
use Stringable;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException as DiInvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

/**
 * 2.74: `page_parameters` as a map, `page_defaults` and `page_resolver` of `remote_dialog.direct_get` — the entry and
 * its validation, the configuration and wiring (BC: a 2.73 configuration gets no new service), the resolver check of
 * the container build, the route check of the cache warmer, and the redirect target for the four Beacon shapes.
 */
final class RemoteDialogPageMappingTest extends TestCase
{
    public function testAListStaysA273EntryAndAMapRenames(): void
    {
        $list = new RemoteDialogContent('step', 'page', 'd', ['tenant', 'id']);
        self::assertSame(['tenant' => 'tenant', 'id' => 'id'], $list->pageParameterMap());
        self::assertFalse($list->usesPageMapping());

        $map = new RemoteDialogContent('step', 'page', 'd', ['id' => 'projectId', 'tenant']);
        self::assertSame(['id' => 'projectId', 'tenant' => 'tenant'], $map->pageParameterMap());
        self::assertTrue($map->usesPageMapping());

        $defaults = new RemoteDialogContent('step', 'page', 'd', [], false, ['section' => 'access', 'n' => 2]);
        self::assertSame(['section' => 'access', 'n' => 2], $defaults->pageDefaults);
        self::assertTrue($defaults->usesPageMapping());

        $resolver = new RemoteDialogContent('step', 'page', 'd', [], false, [], 'App\\Dialog\\DayResolver');
        self::assertSame('App\\Dialog\\DayResolver', $resolver->pageResolver);
        self::assertTrue($resolver->usesPageMapping());
    }

    /**
     * @return iterable<string, array{0: array<mixed>, 1: array<mixed>, 2: string|null, 3: string}>
     */
    public static function invalidMappings(): iterable
    {
        yield 'map key with a bracket' => [['x[y]' => 'id'], [], null, 'must map page route parameter names'];
        yield 'map value with a bracket' => [['id' => 'x[y]'], [], null, 'must be route parameter names'];
        yield 'default key not a name' => [[], ['a-b' => 'x'], null, 'page_defaults of remote dialog route'];
        yield 'default integer key' => [[], ['x'], null, 'page_defaults of remote dialog route'];
        yield 'default value an array' => [[], ['section' => ['x']], null, 'string or an integer'];
        yield 'default value a boolean' => [[], ['section' => true], null, 'string or an integer'];
        yield 'default value too long' => [[], ['section' => str_repeat('a', 513)], null, 'string or an integer'];
        yield 'mapped and defaulted' => [['id' => 'projectId'], ['id' => '1'], null, 'both in page_parameters and in page_defaults'];
        yield 'listed and defaulted' => [['id'], ['id' => '1'], null, 'both in page_parameters and in page_defaults'];
        yield 'resolver id with a space' => [[], [], 'my resolver', 'must be a service id'];
        yield 'resolver id empty' => [[], [], '', 'must be a service id'];
    }

    /**
     * @param array<mixed> $parameters
     * @param array<mixed> $defaults
     */
    #[DataProvider('invalidMappings')]
    public function testInvalidMappingsAreRefused(array $parameters, array $defaults, ?string $resolver, string $message): void
    {
        self::assertStringContainsString($message, (string) RemoteDialogContent::invalid('step', 'page', 'd', $parameters, $defaults, $resolver));
        $this->expectException(InvalidArgumentException::class);
        /* @phpstan-ignore argument.type (invalid on purpose) */
        new RemoteDialogContent('step', 'page', 'd', $parameters, false, $defaults, $resolver);
    }

    public function testMapHelpers(): void
    {
        $map = new RemoteDialogContentMap([
            'a' => ['page' => 'p', 'dialog' => 'd', 'page_parameters' => ['x']],
            'b' => ['page' => 'p', 'dialog' => 'd', 'page_resolver' => 'z.resolver'],
            'c' => ['page' => 'p', 'dialog' => 'd', 'page_resolver' => 'a.resolver', 'page_defaults' => ['s' => 'x']],
            'e' => ['page' => 'p', 'dialog' => 'd', 'page_resolver' => 'z.resolver'],
        ]);
        self::assertSame(['a.resolver', 'z.resolver'], $map->resolvers());
        self::assertSame(['b', 'c', 'e'], array_keys($map->mappedEntries()));
        self::assertSame([], (new RemoteDialogContentMap(['a' => ['page' => 'p', 'dialog' => 'd']]))->resolvers());
    }

    public function testAPageParameterNamedLikeTheReopenParameterIsRefused(): void
    {
        $listed = new RemoteDialogContent('a', 'p', 'd', ['_dialog']);
        // A 2.73 list is left as it was (BC); only the 2.74 options are checked.
        self::assertNull(RemoteDialogContentMap::reopenClash(['a' => $listed], '_dialog'));
        self::assertNotNull(RemoteDialogContentMap::reopenClash(['a' => new RemoteDialogContent('a', 'p', 'd', ['_dialog' => 'id'])], '_dialog'));
        self::assertNotNull(RemoteDialogContentMap::reopenClash(['a' => new RemoteDialogContent('a', 'p', 'd', [], false, ['reopen' => 'x'])], 'reopen'));
        self::assertNull(RemoteDialogContentMap::reopenClash(['a' => new RemoteDialogContent('a', 'p', 'd', [], false, ['reopen' => 'x'])], '_dialog'));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('name of the reopen query parameter');
        new RemoteDialogContentMap(['a' => ['page' => 'p', 'dialog' => 'd', 'page_defaults' => ['_dialog' => 'x']]]);
    }

    public function testA273ConfigurationGetsNoNewServiceAndAnUnchangedMap(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['remote_dialog' => ['direct_get' => ['routes' => [
            'step' => ['page' => 'page', 'dialog' => 'd', 'page_parameters' => ['tenant']],
        ]]]]], $container);

        self::assertFalse($container->hasDefinition('nowo_ui_kit.remote_dialog_page_mapping_check'));
        self::assertFalse($container->hasParameter(RemoteDialogPageResolverPass::PARAMETER));
        self::assertCount(3, $container->getDefinition('nowo_ui_kit.remote_dialog_reopen')->getArguments());
        self::assertSame(
            ['step' => ['page' => 'page', 'dialog' => 'd', 'page_parameters' => ['tenant'], 'redirect_same_origin' => false]],
            $container->getDefinition('nowo_ui_kit.remote_dialog_content_map')->getArgument(0),
        );
    }

    public function testMappingOptionsAreWired(): void
    {
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['remote_dialog' => ['direct_get' => ['routes' => [
            'member_edit' => ['page' => 'settings', 'dialog' => 'd', 'page_parameters' => ['id' => 'projectId'], 'page_defaults' => ['section' => 'access']],
            'contest' => ['page' => 'day', 'dialog' => 'w', 'page_resolver' => 'app.day_resolver'],
        ]]]]], $container);

        self::assertSame([
            'member_edit' => ['page' => 'settings', 'dialog' => 'd', 'page_parameters' => ['id' => 'projectId'], 'page_defaults' => ['section' => 'access'], 'redirect_same_origin' => false],
            'contest' => ['page' => 'day', 'dialog' => 'w', 'page_resolver' => 'app.day_resolver', 'page_parameters' => [], 'redirect_same_origin' => false],
        ], $container->getDefinition('nowo_ui_kit.remote_dialog_content_map')->getArgument(0));
        $check = $container->getDefinition('nowo_ui_kit.remote_dialog_page_mapping_check');
        self::assertTrue($check->hasTag('kernel.cache_warmer'));
        self::assertEquals([new Reference('nowo_ui_kit.remote_dialog_content_map'), new Reference('router')], $check->getArguments());
        self::assertSame(['app.day_resolver'], $container->getParameter(RemoteDialogPageResolverPass::PARAMETER));
        $reopen = $container->getDefinition('nowo_ui_kit.remote_dialog_reopen');
        self::assertInstanceOf(Reference::class, $reopen->getArgument(3));
        self::assertSame('logger', (string) $reopen->getArgument(4));

        $defaultsOnly = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['remote_dialog' => ['direct_get' => ['routes' => [
            'member_edit' => ['page' => 'settings', 'dialog' => 'd', 'page_defaults' => ['section' => 'access']],
        ]]]]], $defaultsOnly);
        self::assertTrue($defaultsOnly->hasDefinition('nowo_ui_kit.remote_dialog_page_mapping_check'));
        self::assertCount(3, $defaultsOnly->getDefinition('nowo_ui_kit.remote_dialog_reopen')->getArguments());
    }

    /**
     * @return iterable<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidMappingConfigs(): iterable
    {
        yield 'mapped and defaulted' => [['routes' => ['a' => ['page' => 'p', 'dialog' => 'd', 'page_parameters' => ['id' => 'x'], 'page_defaults' => ['id' => '1']]]], 'both in page_parameters'];
        yield 'default value a list' => [['routes' => ['a' => ['page' => 'p', 'dialog' => 'd', 'page_defaults' => ['s' => true]]]], 'string or an integer'];
        yield 'resolver not an id' => [['routes' => ['a' => ['page' => 'p', 'dialog' => 'd', 'page_resolver' => 'a b']]], 'service id'];
        yield 'page parameter named like the reopen parameter' => [['query_parameter' => 'reopen', 'routes' => ['a' => ['page' => 'p', 'dialog' => 'd', 'page_parameters' => ['reopen' => 'id']]]], 'reopen query parameter'];
    }

    /**
     * @param array<string, mixed> $directGet
     */
    #[DataProvider('invalidMappingConfigs')]
    public function testInvalidMappingConfigurationFailsTheBuild(array $directGet, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        (new NowoUiKitExtension())->load([['remote_dialog' => ['direct_get' => $directGet]]], new ContainerBuilder());
    }

    public function testTheResolverPassChecksTheServices(): void
    {
        $container = new ContainerBuilder();
        (new RemoteDialogPageResolverPass())->process($container);
        self::assertFalse($container->hasParameter(RemoteDialogPageResolverPass::PARAMETER));

        $container->setParameter(RemoteDialogPageResolverPass::PARAMETER, ['app.ok', 'app.aliased']);
        $container->setParameter('resolver.class', FixedPageResolver::class);
        $container->register('app.ok', '%resolver.class%');
        $container->register(FixedPageResolver::class);
        $container->setAlias('app.aliased', FixedPageResolver::class);
        (new RemoteDialogPageResolverPass())->process($container);
        self::assertFalse($container->hasParameter(RemoteDialogPageResolverPass::PARAMETER));
    }

    public function testTheResolverPassRefusesAMissingService(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(RemoteDialogPageResolverPass::PARAMETER, ['app.missing']);
        $this->expectException(DiInvalidArgumentException::class);
        $this->expectExceptionMessage('service "app.missing" does not exist');
        (new RemoteDialogPageResolverPass())->process($container);
    }

    public function testTheResolverPassRefusesAServiceOfAnotherClass(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(RemoteDialogPageResolverPass::PARAMETER, ['app.wrong']);
        $container->register('app.wrong', stdClass::class);
        $this->expectException(DiInvalidArgumentException::class);
        $this->expectExceptionMessage('(stdClass) must implement');
        (new RemoteDialogPageResolverPass())->process($container);
    }

    public function testTheResolverPassRefusesAClassThatIsNotAString(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(RemoteDialogPageResolverPass::PARAMETER, ['app.odd']);
        $container->setParameter('odd.class', ['x']);
        $container->register('app.odd', '%odd.class%');
        $this->expectException(DiInvalidArgumentException::class);
        $this->expectExceptionMessage('(array) must implement');
        (new RemoteDialogPageResolverPass())->process($container);
    }

    private function beaconRoutes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('project_settings_section', new Route('/projects/{id}/settings/{section}', [], ['section' => 'general|access|notifications']));
        $routes->add('project_access_member_edit', new Route('/projects/{projectId}/access/members/{memberId}'));
        $routes->add('notification_destination_edit', new Route('/projects/{projectId}/notifications/destinations/{id}'));
        $routes->add('admin_project_show', new Route('/admin/projects/{id}'));
        $routes->add('admin_project_edit', new Route('/admin/projects/{projectId}/edit'));
        $routes->add('working_time_day', new Route('/working-time/days/{uuid}'));
        $routes->add('working_time_contest', new Route('/working-time/corrections/{uuid}/contest'));
        $routes->add('optional_page.en', new Route('/en/optional/{page}', ['page' => '1', '_canonical_route' => 'optional_page', '_locale' => 'en']));
        $routes->add('optional_page.es', new Route('/es/opcional/{page}', ['page' => '1', '_canonical_route' => 'optional_page', '_locale' => 'es']));
        $routes->add('with_default', new Route('/with-default/{x}', ['kind' => 'a']));

        return $routes;
    }

    /**
     * @return iterable<string, array{0: RemoteDialogContent}>
     */
    public static function validAgainstRoutes(): iterable
    {
        yield 'project access' => [new RemoteDialogContent('project_access_member_edit', 'project_settings_section', 'd', ['id' => 'projectId'], false, ['section' => 'access'])];
        yield 'notification destinations' => [new RemoteDialogContent('notification_destination_edit', 'project_settings_section', 'd', ['id' => 'projectId'], false, ['section' => 'notifications'])];
        yield 'admin project' => [new RemoteDialogContent('admin_project_edit', 'admin_project_show', 'd', ['id' => 'projectId'])];
        yield 'working time with a resolver' => [new RemoteDialogContent('working_time_contest', 'working_time_day', 'd', [], false, [], 'app.day_resolver')];
        yield 'localized page with an optional parameter' => [new RemoteDialogContent('admin_project_edit', 'optional_page', 'd', [], false, ['page' => 2])];
        yield 'dialog route default read' => [new RemoteDialogContent('with_default', 'admin_project_show', 'd', ['id' => 'kind'])];
    }

    #[DataProvider('validAgainstRoutes')]
    public function testTheCheckAcceptsTheBeaconShapes(RemoteDialogContent $entry): void
    {
        self::assertNull(RemoteDialogPageMappingCheck::check($entry, $this->beaconRoutes()));
    }

    /**
     * @return iterable<string, array{0: RemoteDialogContent, 1: string}>
     */
    public static function invalidAgainstRoutes(): iterable
    {
        yield 'unknown dialog route' => [new RemoteDialogContent('nope', 'admin_project_show', 'd', ['id' => 'projectId']), 'remote dialog route "nope" is not a route'];
        yield 'unknown page' => [new RemoteDialogContent('admin_project_edit', 'nope', 'd', ['id' => 'projectId']), 'owning page "nope"'];
        yield 'mapped dialog parameter unknown' => [new RemoteDialogContent('admin_project_edit', 'admin_project_show', 'd', ['id' => 'project']), 'read "project"'];
        yield 'mapped page parameter unknown' => [new RemoteDialogContent('admin_project_edit', 'admin_project_show', 'd', ['ident' => 'projectId']), 'page parameter "ident"'];
        yield 'default page parameter unknown' => [new RemoteDialogContent('admin_project_edit', 'admin_project_show', 'd', ['id' => 'projectId'], false, ['tab' => 'x']), 'page parameter "tab"'];
        yield 'required page parameter missing' => [new RemoteDialogContent('notification_destination_edit', 'project_settings_section', 'd', ['id' => 'projectId'], false, []), 'requires the parameter "section"'];
        yield 'list with a missing parameter (copied by name)' => [new RemoteDialogContent('project_access_member_edit', 'project_settings_section', 'd', ['projectId'], false, ['section' => 'access']), 'page parameter "projectId"'];
        yield 'default against the requirement' => [new RemoteDialogContent('project_access_member_edit', 'project_settings_section', 'd', ['id' => 'projectId'], false, ['section' => 'billing']), 'does not match the requirement'];
        yield 'resolver with an unknown default' => [new RemoteDialogContent('working_time_contest', 'working_time_day', 'd', [], false, ['day' => 'x'], 'app.day_resolver'), 'page parameter "day"'];
    }

    #[DataProvider('invalidAgainstRoutes')]
    public function testTheCheckRefusesWrongMappings(RemoteDialogContent $entry, string $message): void
    {
        self::assertStringContainsString($message, (string) RemoteDialogPageMappingCheck::check($entry, $this->beaconRoutes()));
    }

    public function testTheWarmerFailsTheBootOnAWrongMappingAndSkips273Entries(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router->expects(self::never())->method('getRouteCollection');
        $check = new RemoteDialogPageMappingCheck(new RemoteDialogContentMap(['missing_route' => ['page' => 'missing_page', 'dialog' => 'd', 'page_parameters' => ['x']]]), $router);
        self::assertFalse($check->isOptional());
        self::assertSame([], $check->warmUp('/tmp'));

        $routerWithRoutes = $this->createStub(RouterInterface::class);
        $routerWithRoutes->method('getRouteCollection')->willReturn($this->beaconRoutes());
        $ok = new RemoteDialogPageMappingCheck(new RemoteDialogContentMap(['admin_project_edit' => ['page' => 'admin_project_show', 'dialog' => 'd', 'page_parameters' => ['id' => 'projectId']]]), $routerWithRoutes);
        self::assertSame([], $ok->warmUp('/tmp'));

        $wrong = new RemoteDialogPageMappingCheck(new RemoteDialogContentMap(['admin_project_edit' => ['page' => 'admin_project_show', 'dialog' => 'd', 'page_parameters' => ['id' => 'nope']]]), $routerWithRoutes);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invalid nowo_ui_kit.remote_dialog.direct_get.routes: the page_parameters of remote dialog route "admin_project_edit" read "nope"');
        $wrong->warmUp('/tmp');
    }

    /**
     * @param array<string, mixed> $routeParams
     */
    private function dialogRequest(string $route, array $routeParams, string $path = '/x'): Request
    {
        $request = Request::create($path);
        $request->attributes->add(['_route' => $route, '_route_params' => $routeParams]);

        return $request;
    }

    /**
     * @param array<string, callable(): object> $resolvers
     */
    private function reopen(array $resolvers = [], ?SpyLogger $logger = null): RemoteDialogReopen
    {
        return new RemoteDialogReopen(new RemoteDialogContentMap([
            'project_access_member_edit' => ['page' => 'project_settings_section', 'dialog' => 'project-settings', 'page_parameters' => ['id' => 'projectId'], 'page_defaults' => ['section' => 'access']],
            'notification_destination_edit' => ['page' => 'project_settings_section', 'dialog' => 'project-settings', 'page_parameters' => ['id' => 'projectId'], 'page_defaults' => ['section' => 'notifications']],
            'admin_project_edit' => ['page' => 'admin_project_show', 'dialog' => 'admin-project', 'page_parameters' => ['id' => 'projectId']],
            'working_time_contest' => ['page' => 'working_time_day', 'dialog' => 'day-dialog', 'page_resolver' => 'app.day_resolver'],
        ]), new UrlGenerator($this->beaconRoutes(), new RequestContext()), null, new ServiceLocator($resolvers), $logger);
    }

    private function target(RemoteDialogReopen $reopen, Request $request): ?string
    {
        $route = $request->attributes->get('_route');
        $entry = $reopen->map()->get(\is_string($route) ? $route : null);
        self::assertInstanceOf(RemoteDialogContent::class, $entry);

        return $reopen->redirectTarget($request, $entry);
    }

    public function testTheFourBeaconShapes(): void
    {
        $day = new FixedPageResolver(['route' => 'working_time_day', 'params' => ['uuid' => 'day-0193']]);
        $reopen = $this->reopen(['app.day_resolver' => static fn (): object => $day]);

        self::assertSame(
            '/projects/p7/settings/access?_dialog%5Broute%5D=project_access_member_edit&_dialog%5Bparams%5D%5BprojectId%5D=p7&_dialog%5Bparams%5D%5BmemberId%5D=m3',
            $this->target($reopen, $this->dialogRequest('project_access_member_edit', ['projectId' => 'p7', 'memberId' => 'm3'])),
        );
        // The content {id} is the destination: it is carried in the reopen query, never copied to the page's {id}.
        self::assertSame(
            '/projects/p7/settings/notifications?_dialog%5Broute%5D=notification_destination_edit&_dialog%5Bparams%5D%5BprojectId%5D=p7&_dialog%5Bparams%5D%5Bid%5D=dest9',
            $this->target($reopen, $this->dialogRequest('notification_destination_edit', ['projectId' => 'p7', 'id' => 'dest9'])),
        );
        self::assertSame(
            '/admin/projects/p7?_dialog%5Broute%5D=admin_project_edit&_dialog%5Bparams%5D%5BprojectId%5D=p7',
            $this->target($reopen, $this->dialogRequest('admin_project_edit', ['projectId' => 'p7'])),
        );
        self::assertSame(
            '/working-time/days/day-0193?_dialog%5Broute%5D=working_time_contest&_dialog%5Bparams%5D%5Buuid%5D=corr-42',
            $this->target($reopen, $this->dialogRequest('working_time_contest', ['uuid' => 'corr-42'])),
        );
        self::assertSame([['working_time_contest', ['uuid' => 'corr-42']]], $day->calls);
    }

    public function testResolverParametersWinOverMappedAndDefaultOnes(): void
    {
        $resolver = new FixedPageResolver(['route' => 'project_settings_section', 'params' => ['section' => 'general', 'id' => 9]]);
        $reopen = new RemoteDialogReopen(new RemoteDialogContentMap([
            'project_access_member_edit' => ['page' => 'project_settings_section', 'dialog' => 'd', 'page_parameters' => ['id' => 'projectId'], 'page_defaults' => ['section' => 'access'], 'page_resolver' => 'r'],
        ]), new UrlGenerator($this->beaconRoutes(), new RequestContext()), null, new ServiceLocator(['r' => static fn (): object => $resolver]));

        self::assertStringStartsWith('/projects/9/settings/general?', (string) $this->target($reopen, $this->dialogRequest('project_access_member_edit', ['projectId' => 'p7', 'memberId' => 'm3'])));
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function answersThatMakeNoRedirect(): iterable
    {
        yield 'null (no record, or not this user\'s)' => [null];
        yield 'another route' => [['route' => 'admin_project_show', 'params' => ['id' => '1']]];
        yield 'no route' => [['params' => ['uuid' => '1']]];
        yield 'params not an array' => [['route' => 'working_time_day', 'params' => 'uuid=1']];
        yield 'nested param' => [['route' => 'working_time_day', 'params' => ['uuid' => ['x']]]];
        yield 'param too long' => [['route' => 'working_time_day', 'params' => ['uuid' => str_repeat('a', 513)]]];
        yield 'integer key' => [['route' => 'working_time_day', 'params' => ['x']]];
        yield 'too many params' => [['route' => 'working_time_day', 'params' => ['uuid' => '1'] + array_fill_keys(array_map(static fn (int $i): string => 'p'.$i, range(1, 32)), 'x')]];
        yield 'missing page parameter' => [['route' => 'working_time_day', 'params' => []]];
    }

    #[DataProvider('answersThatMakeNoRedirect')]
    public function testAResolverAnswerThatIsNotAPageLinkMakesNoRedirect(mixed $answer): void
    {
        $reopen = $this->reopen(['app.day_resolver' => static fn (): object => new FixedPageResolver($answer)]);

        self::assertNull($this->target($reopen, $this->dialogRequest('working_time_contest', ['uuid' => 'corr-42'])));
    }

    public function testAResolverExceptionMakesNoRedirectAndIsLoggedWithoutTheRecord(): void
    {
        $logger = new SpyLogger();
        $reopen = $this->reopen(['app.day_resolver' => static fn (): object => new FixedPageResolver(new RuntimeException('correction corr-42 not found'))], $logger);

        self::assertNull($this->target($reopen, $this->dialogRequest('working_time_contest', ['uuid' => 'corr-42'])));
        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0][0]);
        self::assertSame(['route' => 'working_time_contest', 'exception_class' => RuntimeException::class], $logger->records[0][2]);
        self::assertStringNotContainsString('corr-42', serialize($logger->records));
    }

    public function testAMissingOrWrongResolverServiceMakesNoRedirect(): void
    {
        // No locator entry (the service locator throws): same answer as no page link.
        self::assertNull($this->target($this->reopen(), $this->dialogRequest('working_time_contest', ['uuid' => 'corr-42'])));
        self::assertNull($this->target($this->reopen(['app.day_resolver' => static fn (): object => new stdClass()]), $this->dialogRequest('working_time_contest', ['uuid' => 'corr-42'])));
        // No locator at all (a host that built the service itself).
        $bare = new RemoteDialogReopen(new RemoteDialogContentMap(['working_time_contest' => ['page' => 'working_time_day', 'dialog' => 'd', 'page_resolver' => 'r']]), new UrlGenerator($this->beaconRoutes(), new RequestContext()));
        self::assertNull($this->target($bare, $this->dialogRequest('working_time_contest', ['uuid' => 'corr-42'])));
    }

    public function testAMappedDialogParameterTheRequestLacksMakesNoRedirect(): void
    {
        self::assertNull($this->target($this->reopen(), $this->dialogRequest('admin_project_edit', ['id' => 'p7'])));
    }

    public function testTheResolverLocatorHoldsTheConfiguredIds(): void
    {
        // Wiring sanity: the locator the extension registers holds service closures of the configured ids.
        $container = new ContainerBuilder();
        (new NowoUiKitExtension())->load([['remote_dialog' => ['direct_get' => ['routes' => [
            'contest' => ['page' => 'day', 'dialog' => 'w', 'page_resolver' => 'app.day_resolver'],
        ]]]]], $container);
        $locatorReference = $container->getDefinition('nowo_ui_kit.remote_dialog_reopen')->getArgument(3);
        self::assertInstanceOf(Reference::class, $locatorReference);
        $locator = $container->getDefinition((string) $locatorReference);
        $services = $locator->getArgument(0);
        self::assertIsArray($services);
        self::assertSame(['app.day_resolver'], array_keys($services));
        self::assertInstanceOf(ServiceClosureArgument::class, $services['app.day_resolver']);
    }
}

/**
 * @internal
 */
final class FixedPageResolver implements RemoteDialogPageResolverInterface
{
    /** @var list<array{0: string, 1: array<string, string>}> */
    public array $calls = [];

    public function __construct(private readonly mixed $answer = null)
    {
    }

    public function resolve(string $route, array $params): ?array
    {
        $this->calls[] = [$route, $params];
        if ($this->answer instanceof Throwable) {
            throw $this->answer;
        }

        /* @phpstan-ignore return.type (odd answers on purpose) */
        return $this->answer;
    }
}

/**
 * @internal
 */
final class SpyLogger extends AbstractLogger
{
    /** @var list<array{0: mixed, 1: string, 2: array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = [$level, (string) $message, $context];
    }
}
