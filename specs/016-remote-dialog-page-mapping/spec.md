# Feature Specification: Remote dialog owning page parameters (map, defaults, resolver)

**Feature Branch**: `feat/remote-dialog-page-mapping`  
**Created**: 2026-11-24  
**Status**: Implemented (target 2.74.0)  
**Input**: Beacon request, follow-up of D-20261124-05 ("remote dialog direct GET", shipped in 2.73.0, spec 015): in
2.73 `remote_dialog.direct_get` copies `page_parameters` from the dialog route to the owning page by identical name
only. Beacon's dialogs need the owning page's parameters under other names (`{projectId}` → `{id}`), fixed values
(`section: access`) and, for a working-time correction, a value that comes from the record (the day's uuid).

**Related docs**: [`docs/USAGE.md`](../../docs/USAGE.md#owning-page-parameters-map-defaults-resolver-274),
[`docs/CONFIGURATION.md`](../../docs/CONFIGURATION.md), [`docs/SECURITY.md`](../../docs/SECURITY.md),
[`docs/UPGRADING.md`](../../docs/UPGRADING.md), [spec 015](../015-remote-dialog-direct-get/spec.md)

---

## Summary

Each `remote_dialog.direct_get.routes.<route>` entry gains three opt-in ways to build the owning page's route
parameters: `page_parameters` as a map `{pageParam: dialogParam}` (the 2.73 list stays), `page_defaults` (fixed
values) and `page_resolver` (a `RemoteDialogPageResolverInterface` service). Wrong mappings fail the container build
(configuration, compiler pass, non-optional cache warmer against the route collection). Entries without the new options
behave and wire exactly as in 2.73.

## User scenarios

- **US-1 (renamed parameter + fixed section)** — Given `project_access_member_edit` `/projects/{projectId}/access/members/{memberId}`
  owned by `project_settings_section` `/projects/{id}/settings/{section}` with `page_parameters: {id: projectId}` and
  `page_defaults: {section: access}`, when the URL is opened from the address bar, then the browser lands on
  `/projects/<projectId>/settings/access` with the dialog open on that member.
- **US-2 (content id not copied)** — Given `notification_destination_edit` `/projects/{projectId}/notifications/destinations/{id}`
  with `{id: projectId}` and `section: notifications`, then the page's `{id}` is the project, never the destination id
  (which still travels in the reopen query).
- **US-3 (admin project)** — Given `admin_project_edit` `/admin/projects/{projectId}/edit` owned by `admin_project_show`
  `/admin/projects/{id}` with `{id: projectId}`, then the redirect goes to `/admin/projects/<projectId>`.
- **US-4 (record-based page)** — Given a working-time correction contest `{uuid}` owned by `working_time_day` `{uuid}`
  with a `page_resolver`, then the redirect goes to the day the correction belongs to; a correction the user may not
  see stays `404` and the resolver never runs; a resolver miss or failure keeps the route's own page.
- **US-5 (wrong configuration)** — Given a map naming a parameter the routes do not have, or a page whose required
  parameter is neither mapped nor defaulted, or a resolver service that does not implement the interface, then the
  kernel does not boot and the message names the entry.

## Requirements

- **FR-PAGEMAP-001 (map)** — `page_parameters` accepts a list (copied by identical name, 2.73) or a map
  `{pageParam: dialogParam}`; list items and map entries can be mixed (an item `name` is `{name: name}`). Unmapped dialog
  parameters are not copied to the page; the reopen query still carries every dialog parameter and query value.
- **FR-PAGEMAP-002 (defaults)** — `page_defaults` `{pageParam: value}`: strings or integers of at most 512 characters,
  merged into the page route parameters. A page parameter is mapped or defaulted, not both.
- **FR-PAGEMAP-003 (resolver)** — `page_resolver: <service id>`, a `RemoteDialogPageResolverInterface`
  (`resolve(string $route, array $params): ?array{route, params}`), receives the dialog route name and its route
  parameters as strings, and runs only when the listener converts a direct GET (after the firewall and the controller's
  access check answered `200`). Its `params` are merged over mapped and default ones. `null`, an exception, another
  route than the entry's `page`, or values that cannot be carried whole (nested, > 512 characters, > 32 values) make no
  redirect: the same answer as "no page link" (no existence oracle). An exception is logged as a warning with the route
  name and exception class only.
- **FR-PAGEMAP-004 (build-time validation)** — The configuration refuses invalid names and values, a parameter both
  mapped and defaulted, an invalid service id and a mapped or default page parameter named like `query_parameter`.
  `RemoteDialogPageResolverPass` refuses a missing resolver service or one that does not implement the interface.
  `RemoteDialogPageMappingCheck` (non-optional cache warmer, run by the kernel right after it builds a new container and
  on `cache:warmup`) checks entries that use the new options against the route collection: both routes exist (localized
  routes through their `.<locale>` variants), mapped page parameters and defaults are page route variables, mapped
  dialog parameters are dialog route variables or defaults, defaults match the page route's requirements, and without a
  resolver every required page variable is mapped or defaulted.
- **FR-PAGEMAP-005 (BC, worker)** — An entry without the new options reaches the container unchanged (no
  `page_defaults` / `page_resolver` keys, no warmer, no locator, same `RemoteDialogReopen` arguments) and produces the
  same redirect as 2.73. Every new service is stateless and `readonly`; resolvers are fetched from a service locator per
  call; nothing is kept between requests (FrankenPHP worker mode, kernel not reset).

## Success criteria

- **SC-1** — `tests/Unit/Dialog/RemoteDialogPageMappingTest.php`: entry and map helpers, invalid mappings, config
  wiring and BC, compiler pass, route check (four Beacon shapes valid; each error case), redirect targets of the four
  shapes, resolver answers that make no redirect, exception logging without the record.
- **SC-2** — `tests/Integration/RemoteDialogPageMappingKernelTest.php`: booted kernel with the four Beacon shapes
  (redirect + owning page reopen target), denied correction keeps `404`, resolver miss / failure keeps `200`, dialog
  fetch unchanged, three boot failures (unknown parameter, missing required page parameter, resolver of another class).
- **SC-3** — Existing 2.73 tests unchanged and green; PHP line coverage stays 100 %.

## Non-goals

- A resolver for the dialog side (the reopen target is still generated from the dialog route and its parameters).
- An owning page chosen per record among several pages (the resolver must return the entry's `page`, so the page's
  dialog safe list stays enumerable from the configuration).
- Front-end changes: none (the reopen contract of the owning page is unchanged).

## Validation

`make test`, `make test-coverage`, `make phpstan`, `make cs-check`, `make igor`.
