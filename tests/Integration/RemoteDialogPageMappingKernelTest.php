<?php

declare(strict_types=1);

namespace Nowo\UiKitBundle\Tests\Integration;

use LogicException;
use Nowo\UiKitBundle\Dialog\RemoteDialogPageResolverInterface;
use Nowo\UiKitBundle\NowoUiKitBundle;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Environment;

/**
 * 2.74: `page_parameters` as a map, `page_defaults` and `page_resolver`, end to end in a booted kernel with the four
 * shapes Beacon needs — project settings sections (`{projectId}` → `{id}` + `section: access`), notification
 * destinations (`section: notifications`, the content `{id}` not copied), the admin project dialog (`{projectId}` →
 * `{id}`) and a working-time correction whose page is its day (resolver). Denied requests and resolver misses keep the
 * route's own answer; a wrong mapping or resolver fails the boot.
 */
final class RemoteDialogPageMappingKernelTest extends TestCase
{
    private const NAVIGATE = ['HTTP_SEC_FETCH_MODE' => 'navigate', 'HTTP_SEC_FETCH_DEST' => 'document'];

    private static ?PageMappingTestKernel $kernel = null;

    /** @var list<string> */
    private static array $dirs = [];

    public static function setUpBeforeClass(): void
    {
        self::$kernel = new PageMappingTestKernel(self::dir());
        self::$kernel->boot();
    }

    public static function tearDownAfterClass(): void
    {
        self::$kernel?->shutdown();
        self::$kernel = null;
        (new Filesystem())->remove(self::$dirs);
        self::$dirs = [];
    }

    private static function dir(): string
    {
        $dir = sys_get_temp_dir().'/nowo-ui-kit-page-mapping-'.bin2hex(random_bytes(4));
        self::$dirs[] = $dir;

        return $dir;
    }

    /**
     * @param array<string, string> $server
     */
    private function request(string $uri, array $server = self::NAVIGATE): Response
    {
        \assert(self::$kernel instanceof PageMappingTestKernel);
        $response = self::$kernel->handle(Request::create($uri, 'GET', [], [], [], $server));
        self::$kernel->terminate(Request::create($uri), $response);

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function query(string $location): array
    {
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);

        return $query;
    }

    public function testProjectSettingsSectionMapsProjectIdToIdWithAFixedSection(): void
    {
        $response = $this->request('/projects/p7/access/members/m3');

        self::assertSame(302, $response->getStatusCode());
        $location = (string) $response->headers->get('Location');
        self::assertStringStartsWith('/projects/p7/settings/access?', $location);
        self::assertSame(['_dialog' => ['route' => 'project_access_member_edit', 'params' => ['projectId' => 'p7', 'memberId' => 'm3']]], $this->query($location));

        $page = (string) $this->request($location)->getContent();
        self::assertStringContainsString('data-nowo-ui-remote-form-reopen-routes="notification_destination_edit project_access_member_edit"', $page);
        self::assertStringContainsString('data-nowo-ui-remote-form-reopen-url="/projects/p7/access/members/m3"', $page);
    }

    public function testNotificationDestinationsDoNotCopyTheContentIdByName(): void
    {
        $location = (string) $this->request('/projects/p7/notifications/destinations/dest9')->headers->get('Location');

        self::assertStringStartsWith('/projects/p7/settings/notifications?', $location);
        self::assertSame(['_dialog' => ['route' => 'notification_destination_edit', 'params' => ['projectId' => 'p7', 'id' => 'dest9']]], $this->query($location));
        $page = (string) $this->request($location)->getContent();
        self::assertStringContainsString('data-nowo-ui-remote-form-reopen-url="/projects/p7/notifications/destinations/dest9"', $page);
    }

    public function testAdminProjectDialogMapsProjectIdToId(): void
    {
        $location = (string) $this->request('/admin/projects/p7/edit')->headers->get('Location');

        self::assertSame('/admin/projects/p7?_dialog%5Broute%5D=admin_project_edit&_dialog%5Bparams%5D%5BprojectId%5D=p7', $location);
        self::assertStringContainsString('data-nowo-ui-remote-form-reopen-url="/admin/projects/p7/edit"', (string) $this->request($location)->getContent());
    }

    public function testWorkingTimeCorrectionLandsOnItsDayThroughTheResolver(): void
    {
        $location = (string) $this->request('/working-time/corrections/corr-42/contest')->headers->get('Location');

        self::assertStringStartsWith('/working-time/days/day-2026-11-24?', $location);
        self::assertSame(['_dialog' => ['route' => 'working_time_contest', 'params' => ['uuid' => 'corr-42']]], $this->query($location));
        self::assertStringContainsString('data-nowo-ui-remote-form-reopen-url="/working-time/corrections/corr-42/contest"', (string) $this->request($location)->getContent());
    }

    public function testADeniedCorrectionKeepsItsNotFoundAndTheResolverNeverRuns(): void
    {
        $response = $this->request('/working-time/corrections/hidden/contest');

        self::assertSame(404, $response->getStatusCode());
        self::assertFalse($response->headers->has('Location'));
        self::assertNotContains('hidden', DayOfCorrectionResolver::$asked);
    }

    public function testAResolverMissOrFailureKeepsTheRouteOwnAnswer(): void
    {
        // The controller allowed it (200), the resolver finds no day (null) or fails: exactly as with no page link.
        foreach (['orphan', 'broken'] as $uuid) {
            $response = $this->request('/working-time/corrections/'.$uuid.'/contest');
            self::assertSame(200, $response->getStatusCode(), $uuid);
            self::assertSame('<form data-correction="'.$uuid.'"></form>', $response->getContent(), $uuid);
            self::assertFalse($response->headers->has('Location'), $uuid);
        }
    }

    public function testADialogFetchStillGetsTheFragment(): void
    {
        $response = $this->request('/projects/p7/access/members/m3', ['HTTP_X_NOWO_DIALOG' => '1', 'HTTP_SEC_FETCH_MODE' => 'same-origin']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<form data-member="m3"></form>', $response->getContent());
    }

    public function testAWrongMappingFailsTheBoot(): void
    {
        $kernel = new PageMappingTestKernel(self::dir(), ['admin_project_edit' => ['page' => 'admin_project_show', 'dialog' => 'admin-project', 'page_parameters' => ['id' => 'project']]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('read "project", which is not a parameter of that route');
        $kernel->boot();
    }

    public function testAMissingRequiredPageParameterFailsTheBoot(): void
    {
        $kernel = new PageMappingTestKernel(self::dir(), ['notification_destination_edit' => ['page' => 'project_settings_section', 'dialog' => 'project-settings', 'page_parameters' => ['id' => 'projectId']]]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('requires the parameter "section"');
        $kernel->boot();
    }

    public function testAResolverThatIsNotOneFailsTheBoot(): void
    {
        $kernel = new PageMappingTestKernel(self::dir(), ['working_time_contest' => ['page' => 'working_time_day', 'dialog' => 'day-dialog', 'page_resolver' => 'logger']]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('service "logger" (Psr\Log\NullLogger) must implement');
        $kernel->boot();
    }
}

/**
 * @internal resolves the day of a working-time correction (the host's own lookup; read-only, stateless per call)
 */
final class DayOfCorrectionResolver implements RemoteDialogPageResolverInterface
{
    /** @var list<string> test probe only */
    public static array $asked = [];

    public function resolve(string $route, array $params): ?array
    {
        $uuid = $params['uuid'] ?? '';
        self::$asked[] = $uuid;
        if ('broken' === $uuid) {
            throw new RuntimeException('lookup failed');
        }

        return 'corr-42' === $uuid ? ['route' => 'working_time_day', 'params' => ['uuid' => 'day-2026-11-24']] : null;
    }
}

/**
 * @internal
 */
final class PageMappingTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed>|null $routes replaces the Beacon entries (boot failure cases)
     */
    public function __construct(private readonly string $dir, private readonly ?array $routes = null)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new NowoUiKitBundle();
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function getCacheDir(): string
    {
        return $this->dir.'/cache';
    }

    public function getLogDir(): string
    {
        return $this->dir.'/log';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', ['test' => true, 'secret' => 'test', 'http_method_override' => false, 'router' => ['utf8' => true]]);
        $container->loadFromExtension('twig', ['default_path' => '%kernel.project_dir%/templates']);
        $container->register('logger', NullLogger::class);
        $container->register('app.day_of_correction', DayOfCorrectionResolver::class);
        $container->loadFromExtension('nowo_ui_kit', ['remote_dialog' => ['direct_get' => ['routes' => $this->routes ?? [
            'project_access_member_edit' => ['page' => 'project_settings_section', 'dialog' => 'project-settings', 'page_parameters' => ['id' => 'projectId'], 'page_defaults' => ['section' => 'access']],
            'notification_destination_edit' => ['page' => 'project_settings_section', 'dialog' => 'project-settings', 'page_parameters' => ['id' => 'projectId'], 'page_defaults' => ['section' => 'notifications']],
            'admin_project_edit' => ['page' => 'admin_project_show', 'dialog' => 'admin-project', 'page_parameters' => ['id' => 'projectId']],
            'working_time_contest' => ['page' => 'working_time_day', 'dialog' => 'day-dialog', 'page_resolver' => 'app.day_of_correction'],
        ]]]]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('project_settings_section', '/projects/{id}/settings/{section}')->controller('kernel::page')->requirements(['section' => 'general|access|notifications'])->defaults(['dialog' => 'project-settings']);
        $routes->add('project_access_member_edit', '/projects/{projectId}/access/members/{memberId}')->controller('kernel::member');
        $routes->add('notification_destination_edit', '/projects/{projectId}/notifications/destinations/{id}')->controller('kernel::destination');
        $routes->add('admin_project_show', '/admin/projects/{id}')->controller('kernel::page')->defaults(['dialog' => 'admin-project']);
        $routes->add('admin_project_edit', '/admin/projects/{projectId}/edit')->controller('kernel::adminEdit');
        $routes->add('working_time_day', '/working-time/days/{uuid}')->controller('kernel::page')->defaults(['dialog' => 'day-dialog']);
        $routes->add('working_time_contest', '/working-time/corrections/{uuid}/contest')->controller('kernel::contest');
    }

    public function page(Environment $twig, string $dialog): Response
    {
        return new Response($twig->render('@NowoUiKitBundle/partials/_remote_form_dialog.html.twig', ['id' => $dialog]));
    }

    public function member(string $memberId): Response
    {
        return new Response(\sprintf('<form data-member="%s"></form>', $memberId));
    }

    public function destination(string $id): Response
    {
        return new Response(\sprintf('<form data-destination="%s"></form>', $id));
    }

    public function adminEdit(string $projectId): Response
    {
        return new Response(\sprintf('<form data-project="%s"></form>', $projectId));
    }

    public function contest(string $uuid): Response
    {
        // The controller's own access check runs first: a correction the user may not see is a 404.
        if ('hidden' === $uuid) {
            throw new NotFoundHttpException();
        }

        return new Response(\sprintf('<form data-correction="%s"></form>', $uuid));
    }
}
