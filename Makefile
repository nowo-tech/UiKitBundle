.PHONY: test cs-check cs-fix phpstan qa assets assets-test

test:
	composer test

cs-check:
	composer cs-check

cs-fix:
	composer cs-fix

phpstan:
	composer phpstan

assets:
	pnpm install --frozen-lockfile || pnpm install
	pnpm run build

assets-test:
	pnpm run test

qa:
	composer qa
	pnpm run typecheck
	pnpm run test
	pnpm run build
