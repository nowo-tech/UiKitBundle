<?php

declare(strict_types=1);

/**
 * PHP-CS-Fixer configuration (PSR-12 + Symfony canonical, REQ-CS-001).
 * Run: make cs-check | make cs-fix.
 */
use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfig;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->name('*.php');

return (new Config())
    ->setParallelConfig(new ParallelConfig(2))
    ->setRiskyAllowed(true)
    ->setRules([
        '@PSR12'                 => true,
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
