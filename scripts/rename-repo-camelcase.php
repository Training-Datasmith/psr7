<?php

declare(strict_types=1);

/**
 * Renames repo-owned camelCase identifiers to snake_case in src/ and tests/.
 * Only renames function/method names and variables; never class/type T_STRING uses.
 */

$root = dirname(__DIR__);
$allowlistPath = $root . '/scripts/upstream-identifier-allowlist.txt';

/** @var array<string, true> */
$allowlist = [];
foreach (file($allowlistPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
        continue;
    }
    $allowlist[$line] = true;
}

function camel_to_snake(string $name): string
{
    $name = str_replace(['UTF8', 'CLosed', 'FRead'], ['Utf8', 'Closed', 'Fread'], $name);
    $name = preg_replace('/(?<=[a-z])(?=[A-Z])/', '_', $name);
    $name = preg_replace('/(?<=[A-Z])(?=[A-Z][a-z])/', '_', $name);

    return strtolower((string) $name);
}

function test_method_to_snake(string $name): string
{
    if (strpos($name, 'test') !== 0) {
        return $name;
    }
    $rest = substr($name, 4);
    if ($rest !== '' && $rest[0] === '_') {
        $rest = substr($rest, 1);
    }

    return 'test_' . camel_to_snake($rest);
}

/**
 * @return list<array{kind: string, name: string}>
 */
function collect_rename_targets(string $code): array
{
    $tokens = token_get_all($code);
    $targets = [];
    $expectFunctionName = false;

    foreach ($tokens as $token) {
        if (!is_array($token)) {
            $expectFunctionName = false;
            continue;
        }

        [$id, $text] = $token;

        if ($id === T_FUNCTION) {
            $expectFunctionName = true;
            continue;
        }

        if ($expectFunctionName && $id === T_STRING) {
            if (preg_match('/[a-z][A-Z]/', $text)) {
                $targets[] = ['kind' => 'function', 'name' => $text];
            }
            $expectFunctionName = false;
            continue;
        }

        if ($expectFunctionName && $id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
            $expectFunctionName = false;
        }

        if ($id === T_VARIABLE) {
            $name = substr($text, 1);
            if (preg_match('/[a-z][A-Z]/', $name)) {
                $targets[] = ['kind' => 'variable', 'name' => $name];
            }
        }
    }

    return $targets;
}

/**
 * @param array<string, string> $map
 */
function apply_identifier_map(string $code, array $map): string
{
    if ($map === []) {
        return $code;
    }

    uksort($map, static function (string $a, string $b): int {
        return strlen($b) <=> strlen($a);
    });

    $tokens = token_get_all($code);
    $out = '';
    $expectFunctionName = false;
    $expectMethodName = false;

    foreach ($tokens as $token) {
        if (!is_array($token)) {
            if ($token === '(') {
                $expectMethodName = false;
            }
            $out .= $token;
            $expectFunctionName = false;
            continue;
        }

        [$id, $text] = $token;

        if ($id === T_FUNCTION) {
            $out .= $text;
            $expectFunctionName = true;
            continue;
        }

        if ($expectFunctionName && $id === T_STRING && isset($map[$text])) {
            $out .= $map[$text];
            $expectFunctionName = false;
            continue;
        }

        if ($expectFunctionName && $id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
            $expectFunctionName = false;
        }

        if ($id === T_OBJECT_OPERATOR || $id === T_DOUBLE_COLON) {
            $out .= $text;
            $expectMethodName = true;
            continue;
        }

        if ($expectMethodName && $id === T_STRING) {
            $out .= isset($map[$text]) ? $map[$text] : $text;
            $expectMethodName = false;
            continue;
        }

        if ($expectMethodName && $id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
            $expectMethodName = false;
        }

        if ($id === T_VARIABLE) {
            $name = substr($text, 1);
            if (isset($map[$name])) {
                $out .= '$' . $map[$name];
                continue;
            }
        }

        $out .= $text;
    }

    return $out;
}

/**
 * @param array<string, string> $map
 */
function patch_docblock_references(string $code, array $map): string
{
    return preg_replace_callback(
        '/@dataProvider\s+([A-Za-z0-9_]+)/',
        static function (array $m) use ($map): string {
            $old = $m[1];
            if (isset($map[$old])) {
                return '@dataProvider ' . $map[$old];
            }

            return $m[0];
        },
        $code
    ) ?? $code;
}

/** @var list<string> */
$paths = [];
foreach (['/src', '/tests'] as $subdir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . $subdir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $paths[] = $file->getPathname();
        }
    }
}

/** @var array<string, string> */
$globalMap = [];

foreach ($paths as $path) {
    $code = file_get_contents($path);
    if ($code === false) {
        fwrite(STDERR, "Could not read {$path}\n");
        exit(1);
    }
    foreach (collect_rename_targets($code) as $target) {
        $identifier = $target['name'];
        if (isset($allowlist[$identifier])) {
            continue;
        }
        if (strpos($identifier, 'test') === 0) {
            $globalMap[$identifier] = test_method_to_snake($identifier);
            continue;
        }
        $globalMap[$identifier] = camel_to_snake($identifier);
    }
}

foreach (array_keys($allowlist) as $allowed) {
    unset($globalMap[$allowed]);
}

foreach ($paths as $path) {
    $code = file_get_contents($path);
    if ($code === false) {
        continue;
    }
    $newCode = patch_docblock_references(apply_identifier_map($code, $globalMap), $globalMap);
    if ($newCode !== $code) {
        file_put_contents($path, $newCode);
    }
}

fwrite(STDOUT, sprintf("rename-repo-camelcase: updated %d identifiers across %d files.\n", count($globalMap), count($paths)));
