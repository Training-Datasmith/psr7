<?php

declare(strict_types=1);

$expected = [
    'psr/http-factory' => [
        'alias' => '1.1.0',
        'reference' => 'f9a0862677e7b1b9bd8dc2ed459f48c7e06ff277',
    ],
    'psr/http-message' => [
        'alias' => '2.0.0',
        'reference' => 'c941fca6736161fe07e79fa83d85217b39411cef',
    ],
];

$root = dirname(__DIR__);
$installedPhp = $root . '/vendor/composer/installed.php';
$installedJson = $root . '/vendor/composer/installed.json';

if (!is_readable($installedPhp) || !is_readable($installedJson)) {
    fwrite(STDERR, "check-cluster-pins: run composer update first (missing vendor/composer metadata).\n");
    exit(1);
}

/**
 * @param array<string, array{alias: string, reference: string}> $map
 * @param array<string, array{reference?: string, aliases?: list<string>}> $versions
 */
function validate_installed_php(array $map, array $versions): bool
{
    $failed = false;
    foreach ($map as $name => $want) {
        if (!isset($versions[$name])) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s is not installed.\n", $name));
            $failed = true;
            continue;
        }
        $pkg = $versions[$name];
        $aliases = $pkg['aliases'] ?? [];
        if (!in_array($want['alias'], $aliases, true)) {
            fwrite(STDERR, sprintf(
                "check-cluster-pins: %s alias mismatch (expected %s in aliases %s).\n",
                $name,
                $want['alias'],
                json_encode($aliases)
            ));
            $failed = true;
        }
        $reference = $pkg['reference'] ?? null;
        if (!is_string($reference) || $reference === '') {
            fwrite(STDERR, sprintf("check-cluster-pins: %s has no reference.\n", $name));
            $failed = true;
            continue;
        }
        if (!hash_equals(strtolower($want['reference']), strtolower($reference))) {
            fwrite(STDERR, sprintf(
                "check-cluster-pins: %s reference mismatch (expected %s, got %s).\n",
                $name,
                $want['reference'],
                $reference
            ));
            $failed = true;
        }
    }

    return !$failed;
}

/**
 * @param array<string, array{alias: string, reference: string}> $map
 * @param array<string, string> $references
 */
function validate_installed_json(array $map, array $references): bool
{
    $failed = false;
    foreach ($map as $name => $want) {
        if (!isset($references[$name])) {
            fwrite(STDERR, sprintf("check-cluster-pins: %s missing from installed.json.\n", $name));
            $failed = true;
            continue;
        }
        if (!hash_equals(strtolower($want['reference']), $references[$name])) {
            fwrite(STDERR, sprintf(
                "check-cluster-pins: %s installed.json reference mismatch (expected %s, got %s).\n",
                $name,
                $want['reference'],
                $references[$name]
            ));
            $failed = true;
        }
    }

    return !$failed;
}

/**
 * @return array<string, string>
 */
function references_from_installed_json(string $installedJson): array
{
    $raw = file_get_contents($installedJson);
    if ($raw === false) {
        throw new RuntimeException('Could not read installed.json');
    }
    /** @var array{packages?: list<array<string, mixed>>, 'packages-dev'?: list<array<string, mixed>>}|null $data */
    $data = json_decode($raw, true, 512);
    if (!is_array($data)) {
        throw new RuntimeException('Could not decode installed.json');
    }
    $packages = array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []);
    $references = [];
    foreach ($packages as $package) {
        if (!isset($package['name'], $package['source']['reference'])) {
            continue;
        }
        $references[$package['name']] = strtolower((string) $package['source']['reference']);
    }

    return $references;
}

/** @var array{versions?: array<string, array<string, mixed>>} $installed */
$installed = require $installedPhp;
$versions = $installed['versions'] ?? [];

$demoVersions = $versions;
$demoVersions['psr/http-factory']['reference'] = str_repeat('0', 40);
if (validate_installed_php($expected, $demoVersions)) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo for installed.php did not fail.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo failed for installed.php.\n");

$references = references_from_installed_json($installedJson);
$demoRefs = $references;
$demoRefs['psr/http-message'] = str_repeat('1', 40);
if (validate_installed_json($expected, $demoRefs)) {
    fwrite(STDERR, "check-cluster-pins: wrong-SHA demo for installed.json did not fail.\n");
    exit(1);
}
fwrite(STDOUT, "check-cluster-pins: wrong-SHA demo failed for installed.json.\n");

if (!validate_installed_php($expected, $versions) || !validate_installed_json($expected, $references)) {
    exit(1);
}

fwrite(STDOUT, "check-cluster-pins: all cluster packages match expected SHAs and aliases.\n");
exit(0);
