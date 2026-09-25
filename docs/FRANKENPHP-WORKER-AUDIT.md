# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/ui-kit-bundle` (`symfony-bundle`) |
| Audited revision | `a9dee3a` (post-`v1.8.3`) |
| Audit date | 2026-09-25 |
| Method | Manual review of every PHP file under `src/` (bundle class, DI extension, configuration, compiler pass, enums), `Resources/config/services.yaml`, Twig templates for request/user data, plus PHPStan `ruleset-classic` + `ruleset-worker` |
| **Verdict** | ✅ **100% compatible** — safe in FrankenPHP worker mode with **kernel not reset** between requests (scenario B below) |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests, so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all (`reset_kernel=false` / no reboot):** nothing is reset; any per-request state kept in a service would leak into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ N/A | The bundle registers no runtime services (`services.yaml` only sets `_defaults`) |
| Static properties / `static` locals | ✅ | None; enums only have pure static `values()` helpers |
| `ResetInterface` / `kernel.reset` coverage | ✅ N/A | Nothing to reset |
| Request / user / locale captured in services | ✅ | No services; templates read `app.request` / `app.flashes` at render time |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used |
| Doctrine / EntityManager | ✅ N/A | No persistence |
| Output, headers, `exit`, shutdown functions | ✅ | None |
| Resources (files, sockets, cURL) held open | ✅ | None |
| Memory growth across requests | ✅ | No caches or accumulating arrays |
| Blocking I/O and timeouts | ✅ N/A | No I/O at runtime |
| Third-party static state | ✅ | Twig globals are compile-time config scalars (`twig.globals`), identical for every request |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` (clean at audit time) |
| Browser IIFEs / Stimulus peers | ✅ N/A (client) | Module `bound` flags and canvas caches live in the browser, not in the PHP worker |

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| (none registered) | — | — | ✅ | ✅ |

`src/Resources/config/services.yaml` only declares `_defaults`; no class is registered. `NowoUiKitExtension`, `Configuration` and `TwigPathsPass` run only while the container is compiled. The enums `CssFramework`, `IconSet` and `RowActionsDisplay` are backed enums used for config validation.

## Findings

No findings. **No code changes required** for worker / `reset_kernel=false` compatibility.

### Info

- `NowoUiKitExtension::prepend` registers Twig globals `nowo_ui_kit_css_framework`, `nowo_ui_kit_icon_set`, `nowo_ui_kit_row_actions_display`. They are scalar values from the bundle config, the same for every request and user, so Twig caching them for the worker lifetime is correct.
- Templates such as `_pagination.html.twig`, `_flashes.html.twig` and `_toasts.html.twig` read `app.request` and `app.flashes`. `app` is Symfony's `AppVariable`, which resolves these lazily from the current `RequestStack` at render time, so nothing from a previous request is reused.
- Both demos ship a worker Caddyfile (`demo/symfony8/docker/frankenphp/Caddyfile`, `demo/symfony8-tailwind/docker/frankenphp/Caddyfile`) with `php_server { worker { file /app/public/index.php } }` and a standard Symfony Runtime `public/index.php` (kernel factory only).

## Usage recommendations in worker mode

- No special configuration or reset hook is needed for this bundle under `reset_kernel=false`.
- When overriding the bundle templates or adding Twig globals next to the `nowo_ui_kit_*` ones, do not put per-user or per-request values (current user, locale, permissions) in `twig.globals` or in a `GlobalsInterface` extension as scalars; Twig caches globals, and under scenario B they would leak between users. Read them through `app.*` or a Twig function instead.

## Re-audit triggers

Re-run this audit when a change adds: a PHP service (Twig extension, runtime, component, event listener), a `GlobalsInterface` extension or globals computed from the request/user, a cache, or any use of `$_SERVER` / `$_ENV` at runtime.
