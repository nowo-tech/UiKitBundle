<?php

declare(strict_types=1);

/**
 * Read-only PHPDoc gate (`make validate-phpdoc`, part of `make release-check`).
 *
 * Fails (exit 1) when a public method that has a PHPDoc block returns `array` or `iterable` without a `@return`
 * tag: those native types cannot say what they contain, so the tag (with generics) is required. Scalar, void,
 * nullable and class return types are fully expressed natively — php-cs-fixer (`no_superfluous_phpdoc_tags`) removes
 * a redundant `@return` for them, so this gate never asks for one. It never writes files: a release gate must not
 * rewrite the tree it is proving.
 */
$srcDir = dirname(__DIR__).'/src';
$missing = [];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir)) as $file) {
    if (!$file->isFile() || 'php' !== $file->getExtension()) {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);
    if (false === $content) {
        continue;
    }

    preg_match_all(
        '#(/\*\*(?:(?!\*/).)*?\*/)\s*public\s+(?:static\s+)?function\s+(\w+)\s*\([^)]*\)\s*:\s*([^\s{]+)#s',
        $content,
        $matches,
        PREG_SET_ORDER,
    );
    foreach ($matches as [, $doc, $method, $returnType]) {
        if (in_array(ltrim($returnType, '?'), ['array', 'iterable'], true) && !str_contains($doc, '@return')) {
            $missing[] = str_replace($srcDir.'/', '', $path).'::'.$method.'(): '.$returnType;
        }
    }
}

if ([] !== $missing) {
    fwrite(STDERR, "Public methods returning array/iterable without a @return tag:\n  ".implode("\n  ", $missing)."\n");
    exit(1);
}

echo "validate-phpdoc: OK\n";
