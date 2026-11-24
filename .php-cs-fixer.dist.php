<?php

declare(strict_types=1);

/**
 * PHP-CS-Fixer configuration (PER-CS + Symfony canonical, REQ-CS-001 / REQ-CS-009).
 * Run: make cs-check | make cs-fix.
 */
use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfig;
use Symfony\Component\Finder\SplFileInfo;

// REQ-CS-009: the demo PHP too, except the Flex-managed config/bundles.php and config/reference.php.
$demoDirs = array_values(array_filter(
    glob(__DIR__ . '/demo/*/*', \GLOB_ONLYDIR) ?: [],
    static fn (string $dir): bool => \in_array(basename($dir), ['src', 'tests', 'config', 'public'], true),
));

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in($demoDirs)
    ->name('*.php')
    ->filter(static fn (SplFileInfo $file): bool => 1 !== preg_match('#/demo/[^/]+/config/(bundles|reference)\.php$#', str_replace('\\', '/', $file->getPathname())));

return (new Config())
    ->setParallelConfig(new ParallelConfig(2))
    ->setRiskyAllowed(true)
    ->setRules([
        '@PER-CS'                => true,
        '@PER-CS:risky'          => true,
        '@Symfony'               => true,
        '@Symfony:risky'         => true,
        'declare_strict_types'   => true,
        'native_function_invocation' => [
            'include' => ['@compiler_optimized'],
            'scope'   => 'namespaced',
            'strict'  => true,
        ],
        // Imports (REQ-CS-001): ordered, no unused, FQCN -> `use` + short name.
        'ordered_imports' => [
            'imports_order'  => ['class', 'function', 'const'],
            'sort_algorithm' => 'alpha',
        ],
        'no_unused_imports'            => true,
        'fully_qualified_strict_types' => [
            'import_symbols' => true,
        ],
        // Classes are imported; global functions and constants keep the leading backslash written by
        // native_function_invocation (compiler-optimized calls), so both rules never fight.
        'global_namespace_import' => [
            'import_classes'   => true,
            'import_constants' => false,
            'import_functions' => false,
        ],
    ])
    ->setFinder($finder);
