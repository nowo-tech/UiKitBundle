# Demo applications with FrankenPHP (development and production)

This document describes how the **Ui Kit Bundle** demo applications run under **FrankenPHP** in Docker, and how to reproduce **development** (no worker, changes visible on refresh) and **production** (worker mode, cache enabled) configurations.

## Table of contents

- [Overview](#overview)
- [What the demos include](#what-the-demos-include)
- [Development configuration](#development-configuration)
  - [Caddyfile (development)](#caddyfile-development)
  - [PHP and Twig (development)](#php-and-twig-development)
  - [Start (development)](#start-development)
- [Production configuration](#production-configuration)
- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [Troubleshooting](#troubleshooting)

---

## Overview

The `demo/` folder is **not shipped** in the Composer package (`archive.exclude` includes `/demo`). Demos exist only in the source repository for development and documentation.

Demos use:

- **FrankenPHP** (Caddy + PHP) in a single container
- **Docker Compose** with the app and parent bundle mounted (`../..` → `/var/ui-kit-bundle`)
- **Two Caddyfiles**: `Caddyfile` (production, with worker) and `Caddyfile.dev` (development, no worker)
- An **entrypoint** that selects classic vs worker Caddyfile from **`FRANKENPHP_MODE`** (`classic` \| `worker`, default **`worker`** in `.env.example`)

There are two Symfony demos:

| Demo | UI theme | Default port |
| --- | --- | --- |
| `demo/symfony8/` | Bootstrap 5 (`css_framework: bootstrap5`) | **8092** |
| `demo/symfony8-tailwind/` | Tailwind (`css_framework: tailwind`) | **8093** |

From the bundle root:

```bash
make -C demo up-symfony8
# Demo started at: http://localhost:8092

make -C demo up-symfony8-tailwind
# Demo started at: http://localhost:8093
```

| Aspect | Development (default) | Production |
| --- | --- | --- |
| FrankenPHP worker | **Off** (`FRANKENPHP_MODE=classic`) | **On** (`worker`) |
| Twig cache | Prefer off in `dev` | **On** |
| OPcache revalidation | Every request (`docker/php-dev.ini`) | Default |
| HTTP cache headers | `no-store` in `Caddyfile.dev` | Omitted |
| `APP_ENV` / `APP_DEBUG` | `dev` / `1` | `prod` / `0` |

---

## What the demos include

Configured for local development and debugging:

- **Symfony Web Profiler** — `dev` and `test` environments
- **Nowo Twig Inspector** (`nowo-tech/twig-inspector-bundle`) and **Nowo Hot Reload** (`nowo-tech/hot-reload-bundle`) — required together on FrankenPHP demos (dev/test only; Caddyfile Mercure + `hot_reload`, plus `worker { watch }` in worker mode). Do not enable Hot Reload in production.
- **Symfony Debug bundle** — `dev` and `test`
- **Nowo Twig Inspector Bundle** — Packagist `require-dev` for template debugging
- **Ui Kit Bundle** — kitchen sink page (`KitchenSinkController` → `@NowoUiKitBundle/demo/kitchen_sink.html.twig`). No Doctrine / CRUD admin.

Example `config/bundles.php` (Bootstrap demo):

```php
return [
    Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true],
    Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => ['dev' => true, 'test' => true],
    Symfony\Bundle\DebugBundle\DebugBundle::class => ['dev' => true, 'test' => true],
    Nowo\UiKitBundle\NowoUiKitBundle::class => ['all' => true],
    Nowo\TwigInspectorBundle\NowoTwigInspectorBundle::class => ['dev' => true, 'test' => true],
];
```

---

## Development configuration

### Caddyfile (development)

`docker/frankenphp/Caddyfile.dev` — plain `php_server` (no `worker`):

```caddyfile
{
	skip_install_trust
}

:80 {
	root * /app/public
	encode zstd br gzip
	header Cache-Control "no-store, no-cache, must-revalidate, max-age=0"
	header Pragma "no-cache"
	php_server
}
```

### PHP and Twig (development)

- `docker/php-dev.ini` — `opcache.revalidate_freq=0`
- Prefer Twig cache disabled under `dev`

### Start (development)

```bash
make -C demo/symfony8 up
# REQ-DEMO-005: ends with Demo started at: http://localhost:<PORT>
```

The Makefile runs `composer install`, then prints the URL from `PORT` in `.env` / `.env.example`.

After editing bundle PHP or Twig, refresh the browser. Run `make update-bundle` in the demo directory if autoload or cache must be refreshed.

Path repository: Docker uses `/var/ui-kit-bundle`; host Composer also lists relative `../../` so `composer install` works outside Docker.

---

## Production configuration

Use `docker/frankenphp/Caddyfile` with worker mode:

```caddyfile
php_server {
	worker /app/public/index.php
}
```

Set `APP_ENV=prod`, `APP_DEBUG=0`, do not mount `php-dev.ini`, and warm the cache:

```bash
php bin/console cache:warmup --env=prod
```

---

## Switching classic vs worker (`FRANKENPHP_MODE`)

- **Classic (hot-reload):** `FRANKENPHP_MODE=classic` — entrypoint copies `Caddyfile.dev`
- **Worker (default):** `FRANKENPHP_MODE=worker` — worker Caddyfile
- Keep `APP_ENV=dev` / `APP_DEBUG=1` for Symfony debug tools independently of FrankenPHP mode.

After changing `.env`, recreate with `docker compose up -d` (no rebuild).

Restart after env or Caddyfile changes: `docker compose restart` or `make down && make up`.

---

## Troubleshooting

### Twig or PHP changes do not appear

- Confirm worker mode is **off** in classic/dev (`Caddyfile.dev` has no `worker`)
- Run `make update-bundle` after bundle code changes
- Hard-refresh the browser

### Demo does not start

- Ensure `PORT` (**8092** / **8093**) is free
- Check `docker compose logs php`
- Confirm Docker Compose can pull Packagist packages (demo DNS is `8.8.8.8` / `8.8.4.4`)

For the full FrankenPHP pattern used across Nowo bundles, see also [TwigInspectorBundle — DEMO-FRANKENPHP.md](https://github.com/nowo-tech/TwigInspectorBundle/blob/main/docs/DEMO-FRANKENPHP.md).
