# Makefile for Symfony 8.0 Demo (Ui Kit Bundle)
# Run from this directory: make up, make install, etc.

.PHONY: help up down build install shell logs test test-coverage cache-clear update-bundle ensure-up restart test-e2e test-e2e-sortable test-e2e-widgets test-e2e-mobile test-e2e-image-crop demo-screenshots

help:
	@echo "Symfony 8.0 Demo - Ui Kit Bundle"
	@echo ""
	@echo "  make up             Start containers"
	@echo "  make down           Stop containers"
	@echo "  make build          Build images (no cache)"
	@echo "  make install        Install dependencies (composer install in container)"
	@echo "  make shell          Open shell in PHP container"
	@echo "  make logs           Show container logs"
	@echo "  make test           Run tests"
	@echo "  make test-coverage  Run tests with coverage"
	@echo "  make cache-clear    Clear Symfony cache"
	@echo "  make update-bundle  Update nowo-tech/ui-kit-bundle and clear cache"
	@echo "  make test-e2e       Playwright e2e of the page contract host-theme demo (REQ-DEMO-013)"
	@echo "  make test-e2e-sortable  Sortable e2e: Chromium, Firefox, WebKit, and touch input in Chromium"
	@echo "  make test-e2e-mobile    2.41 phone mode e2e at 390x844 (Chromium, WebKit)"
	@echo "  make test-e2e-image-crop  2.43 image crop e2e: desktop and phone, Chromium and WebKit"
	@echo "  make demo-screenshots  Refresh docs/images/demo/page-contract-*.webp via Playwright"
	@echo ""

up:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@$(call run-compose,up -d)
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(call run-compose,exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction)
	@$(call run-compose,exec -T php php bin/console assets:install public)
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"
down:
	@$(call run-compose,down)

restart: ensure-up
	@$(call run-compose,restart)

build:
	@$(call run-compose,build --no-cache)

ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		echo "Starting container..."; \
		$(COMPOSE) up -d; \
		sleep 3; \
	fi

install: ensure-up
	@$(call run-compose,exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction)

shell: ensure-up
	@$(call run-compose,exec php sh)

logs:
	@$(call run-compose,logs -f)

test: ensure-up
	@$(call run-compose,exec -T -e COMPOSER_MEMORY_LIMIT=-1 php composer install --no-interaction)
	@$(call run-compose,exec -e APP_ENV=test php composer test)

test-coverage: ensure-up
	@$(call run-compose,exec -e APP_ENV=test php composer test-coverage)

cache-clear: ensure-up
	@$(call run-compose,exec php php bin/console cache:clear)

update-bundle: ensure-up
	@$(call run-compose,exec -T php composer dump-autoload --optimize)
	@$(call run-compose,exec php php bin/console cache:clear --env=dev --no-warmup) || true
	@echo "Bundle updated. Reload the page in the browser."


# REQ-DEMO-013: Playwright e2e + WebP screenshots (official image; @playwright/test pinned to the same version).
PLAYWRIGHT_IMAGE ?= mcr.microsoft.com/playwright:v1.63.0-jammy

test-e2e: ensure-up
	@mkdir -p e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); PORT=$${PORT:-8092}; \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=chromium'

# UiKit sortable in Chromium, Firefox and WebKit, and touch input in Chromium. A hang cannot hold the machine: deadline.
test-e2e-sortable: ensure-up
	@mkdir -p e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); PORT=$${PORT:-8092}; \
	timeout $${E2E_DEADLINE:-600} docker run --rm --init --network host \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test sortable --workers=1 --global-timeout=540000 --project=chromium --project=firefox --project=webkit --project=chromium-touch'

# Emoji picker, show more and the dialog back button in Chromium, Firefox and WebKit, with a deadline.
test-e2e-widgets: ensure-up
	@mkdir -p e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); PORT=$${PORT:-8092}; \
	timeout $${E2E_DEADLINE:-420} docker run --rm --init --network host \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test emoji-picker show-more dialog-back --workers=1 --global-timeout=360000 --project=chromium --project=firefox --project=webkit'

# 2.41 phone mode (Beacon mobile UX audit) at 390 x 844 in Chromium and WebKit, with a deadline.
test-e2e-mobile: ensure-up
	@mkdir -p e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); PORT=$${PORT:-8092}; \
	timeout $${E2E_DEADLINE:-420} docker run --rm --init --network host \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test mobile-mux --workers=1 --global-timeout=360000 --project=phone-chromium --project=phone-webkit'

# 2.43 image crop: desktop and phone (touch emulation), Chromium and WebKit, with a deadline.
test-e2e-image-crop: ensure-up
	@mkdir -p e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); PORT=$${PORT:-8092}; \
	timeout $${E2E_DEADLINE:-480} docker run --rm --init --network host \
		-v "$(CURDIR)/e2e:/e2e" -w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test image-crop --workers=2 --global-timeout=420000 --project=chromium --project=webkit --project=phone-chromium --project=phone-webkit'

demo-screenshots: ensure-up
	@mkdir -p ../../docs/images/demo e2e/node_modules
	@PORT=$$(grep -E '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); PORT=$${PORT:-8092}; \
	docker run --rm --network host \
		-v "$(CURDIR)/e2e:/e2e" \
		-v "$(CURDIR)/../../docs/images/demo:/screenshots" \
		-w /e2e \
		-e DEMO_BASE_URL=http://127.0.0.1:$$PORT \
		-e SCREENSHOT_DIR=/screenshots \
		$(PLAYWRIGHT_IMAGE) \
		bash -lc 'npm install --no-fund --no-audit && npx playwright test --project=screenshots && node scripts/to-webp.mjs /screenshots'
	@echo "Screenshots written under docs/images/demo/ (WebP)"

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
# Prefer Compose V2 plugin (GitHub Actions / modern Docker Desktop); fall back to docker-compose V1 (REQ-MAKE-010).
COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
# WSL Docker Desktop: bare execve of `docker` can return EACCES; always run Compose via shell.
run-compose = $(SHELL) -c '$(COMPOSE) $(1)'
SERVICE_PHP := php
BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
