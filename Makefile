.PHONY: test cs-check cs-fix phpstan qa

test:
	composer test

cs-check:
	composer cs-check

cs-fix:
	composer cs-fix

phpstan:
	composer phpstan

qa:
	composer qa
