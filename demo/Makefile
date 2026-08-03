DEMOS := symfony8 symfony8-tailwind

.PHONY: help up down update-bundle test-all release-check release-verify clean
.PHONY: up-symfony8 up-symfony8-tailwind down-symfony8 down-symfony8-tailwind
.PHONY: test-symfony8 test-symfony8-tailwind update-bundle-symfony8 update-bundle-symfony8-tailwind

help:
	@echo "Ui Kit Bundle - Demo Commands"
	@echo ""
	@echo "  make up DEMO=symfony8            Start a demo (default: symfony8)"
	@echo "  make down DEMO=symfony8          Stop a demo"
	@echo "  make update-bundle DEMO=symfony8 Refresh bundle in a demo"
	@echo "  make up-symfony8                 Start Symfony 8 demo (Bootstrap)"
	@echo "  make up-symfony8-tailwind        Start Symfony 8 demo (Tailwind)"
	@echo "  make release-check               test-all + release-verify"
	@echo ""

DEMO ?= symfony8

up:
	@$(MAKE) -C $(DEMO) up

down:
	@$(MAKE) -C $(DEMO) down

update-bundle:
	@$(MAKE) -C $(DEMO) update-bundle

up-symfony8:
	@$(MAKE) -C symfony8 up

up-symfony8-tailwind:
	@$(MAKE) -C symfony8-tailwind up

down-symfony8:
	@$(MAKE) -C symfony8 down

down-symfony8-tailwind:
	@$(MAKE) -C symfony8-tailwind down

test-symfony8:
	@$(MAKE) -C symfony8 test

test-symfony8-tailwind:
	@$(MAKE) -C symfony8-tailwind test

update-bundle-symfony8:
	@$(MAKE) -C symfony8 update-bundle

update-bundle-symfony8-tailwind:
	@$(MAKE) -C symfony8-tailwind update-bundle

test-all: test-symfony8 test-symfony8-tailwind

release-verify:
	@$(MAKE) up-symfony8
	@sleep 10
	@PORT=$$(grep '^PORT=' symfony8/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep '^PORT=' symfony8/.env.example | cut -d= -f2 | tr -d '\r'); \
	code=$$(curl -sL -o /dev/null -w "%{http_code}" http://127.0.0.1:$$PORT 2>/dev/null || echo "000"); \
	$(MAKE) down-symfony8; \
	if [ "$$code" != "200" ]; then echo "Bootstrap demo healthcheck failed (HTTP $$code)"; exit 1; fi; \
	echo "Symfony 8 Bootstrap demo OK (HTTP $$code)"
	@$(MAKE) up-symfony8-tailwind
	@sleep 10
	@PORT=$$(grep '^PORT=' symfony8-tailwind/.env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep '^PORT=' symfony8-tailwind/.env.example | cut -d= -f2 | tr -d '\r'); \
	code=$$(curl -sL -o /dev/null -w "%{http_code}" http://127.0.0.1:$$PORT 2>/dev/null || echo "000"); \
	$(MAKE) down-symfony8-tailwind; \
	if [ "$$code" != "200" ]; then echo "Tailwind demo healthcheck failed (HTTP $$code)"; exit 1; fi; \
	echo "Symfony 8 Tailwind demo OK (HTTP $$code)"

release-check: test-all release-verify
	@echo "All demo checks passed."

clean:
	rm -rf symfony8/vendor symfony8/var symfony8/.phpunit.cache
	rm -rf symfony8-tailwind/vendor symfony8-tailwind/var symfony8-tailwind/.phpunit.cache

BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/..)
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-aggregate-update-deps.mk
