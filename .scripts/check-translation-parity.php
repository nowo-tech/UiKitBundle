<?php

declare(strict_types=1);

/*
 * REQ-MAKE-004: validates every translation file (YAML syntax) and checks key parity across locales.
 * Each domain's flattened key set must be identical in every locale; the reference locale is `en`.
 * Usage: php .scripts/check-translation-parity.php [translations-dir]
 * Exit code: 0 = OK, 1 = syntax error or missing / extra keys.
 */

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

require \dirname(__DIR__).'/vendor/autoload.php';

$dir = $argv[1] ?? \dirname(__DIR__).'/src/Resources/translations';
$reference = 'en';

/**
 * @param array<mixed> $tree
 *
 * @return list<string>
 */
function flattenKeys(array $tree, string $prefix = ''): array
{
    $keys = [];
    foreach ($tree as $key => $value) {
        $path = '' === $prefix ? (string) $key : $prefix.'.'.$key;
        if (is_array($value)) {
            $keys = [...$keys, ...flattenKeys($value, $path)];
        } else {
            $keys[] = $path;
        }
    }

    return $keys;
}

// No GLOB_BRACE: it is missing on musl (Alpine) builds of PHP.
$files = [...(glob(rtrim($dir, '/').'/*.yaml') ?: []), ...(glob(rtrim($dir, '/').'/*.yml') ?: [])];
if ([] === $files) {
    fwrite(STDERR, "No translation files in {$dir}\n");
    exit(1);
}

/** @var array<string, array<string, list<string>>> $domains domain => locale => keys */
$domains = [];
$errors = 0;
foreach ($files as $file) {
    if (1 !== preg_match('/^(?<domain>.+)\.(?<locale>[a-zA-Z_]+)\.ya?ml$/', basename($file), $m)) {
        fwrite(STDERR, "Unexpected translation file name: {$file}\n");
        ++$errors;
        continue;
    }
    try {
        $data = Yaml::parseFile($file);
    } catch (ParseException $e) {
        fwrite(STDERR, "YAML syntax error in {$file}: {$e->getMessage()}\n");
        ++$errors;
        continue;
    }
    $domains[$m['domain']][$m['locale']] = flattenKeys(is_array($data) ? $data : []);
}

foreach ($domains as $domain => $locales) {
    if (!isset($locales[$reference])) {
        fwrite(STDERR, "Domain {$domain}: no reference locale '{$reference}'\n");
        ++$errors;
        continue;
    }
    $expected = $locales[$reference];
    foreach ($locales as $locale => $keys) {
        $missing = array_values(array_diff($expected, $keys));
        $extra = array_values(array_diff($keys, $expected));
        if ([] !== $missing || [] !== $extra) {
            ++$errors;
            fwrite(STDERR, sprintf("%s.%s: %d missing, %d extra key(s) vs %s\n", $domain, $locale, count($missing), count($extra), $reference));
            foreach ($missing as $key) {
                fwrite(STDERR, "  - missing: {$key}\n");
            }
            foreach ($extra as $key) {
                fwrite(STDERR, "  + extra:   {$key}\n");
            }
        }
    }
    printf("%s: %d locale(s), %d key(s) each\n", $domain, count($locales), count($expected));
}

if ($errors > 0) {
    fwrite(STDERR, "Translation validation FAILED ({$errors} problem(s)).\n");
    exit(1);
}

echo "Translation validation OK (YAML syntax + key parity).\n";
