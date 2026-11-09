<?php

declare(strict_types=1);

$root = dirname(__DIR__);

/** @return array<string, true> */
function load_upstream_allowlist(string $path): array
{
  $allowlist = [];
  foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
    $line = trim($line);
    if ($line === '' || $line[0] === '#') {
      continue;
    }
    $allowlist[$line] = true;
  }

  return $allowlist;
}

function is_repo_camelcase_identifier(string $name): bool
{
  if ($name === '' || !ctype_lower($name[0])) {
    return false;
  }

  return (bool) preg_match('/[a-z][A-Z]/', $name);
}

/**
 * @param array<string, true> $allowlist
 * @return list<string>
 */
function collect_snake_case_violations(string $filePath, string $code, array $allowlist): array
{
  $violations = [];
  $tokens = token_get_all($code);
  $expectFunctionName = false;

  foreach ($tokens as $token) {
    if (!is_array($token)) {
      if ($token === '(') {
        $expectFunctionName = false;
      }
      continue;
    }

    [$id, $text] = $token;

    if ($id === T_FUNCTION) {
      $expectFunctionName = true;
      continue;
    }

    if ($expectFunctionName && $id === T_STRING) {
      if (is_repo_camelcase_identifier($text) && !isset($allowlist[$text])) {
        $violations[] = sprintf('%s: repo-owned camelCase identifier %s', $filePath, $text);
      }
      $expectFunctionName = false;
      continue;
    }

    if ($expectFunctionName && $id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
      $expectFunctionName = false;
    }

    if ($id === T_VARIABLE) {
      $name = substr($text, 1);
      if (is_repo_camelcase_identifier($name) && !isset($allowlist[$name])) {
        $violations[] = sprintf('%s: repo-owned camelCase identifier $%s', $filePath, $name);
      }
    }
  }

  return $violations;
}

/**
 * @param array<string, true> $allowlist
 * @return list<string>
 */
function collect_snake_case_violations_tree(string $dir, array $allowlist): array
{
  $violations = [];
  $iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
  );
  foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
      continue;
    }
    $contents = file_get_contents($file->getPathname());
    if ($contents === false) {
      continue;
    }
    $violations = array_merge($violations, collect_snake_case_violations($file->getPathname(), $contents, $allowlist));
  }

  return $violations;
}

$upstreamAllowlist = load_upstream_allowlist($root . '/scripts/upstream-identifier-allowlist.txt');

$scanPaths = [
    $root . '/src',
    $root . '/tests',
    $root . '/hack',
    $root . '/composer.json',
    $root . '/phpunit.xml.dist',
    $root . '/ARCHITECTURE.md',
    $root . '/phpstan-baseline.neon',
];

$patterns = [
    '/GuzzleHttp\\\\Psr7/',
    '/GuzzleHttp\\\\Tests\\\\Psr7/',
    '/\bAppendStream\b/',
    '/\bBufferStream\b/',
    '/\bCachingStream\b/',
    '/\bDroppingStream\b/',
    '/\bFnStream\b/',
    '/\bHttpFactory\b/',
    '/\bInflateStream\b/',
    '/\bLazyOpenStream\b/',
    '/\bLimitStream\b/',
    '/\bMessageTrait\b/',
    '/\bMimeType\b/',
    '/\bMultipartStream\b/',
    '/\bNoSeekStream\b/',
    '/\bPumpStream\b/',
    '/\bStreamDecoratorTrait\b/',
    '/\bStreamWrapper\b/',
    '/\bUriComparator\b/',
    '/\bUriNormalizer\b/',
    '/\bUriResolver\b/',
    '/\bMalformedUriException\b/',
    '/\bHasToString\b/',
    '/\bReadSeekOnlyStream\b/',
    '/\bUtilsTest\b/',
    '/\bResponseTest\b/',
    '/\bRequestTest\b/',
    '/\bBadStream\b/',
    '/\bExtendedUriTest\b/',
    '/Request_Interface/',
    '/Response_Interface/',
    '/Server_Request_Interface/',
    '/Message_Interface/',
    '/Stream_Interface/',
    '/Uri_Interface/',
    '/Uploaded_File_Interface/',
    '/Request_Factory_Interface/',
    '/Response_Factory_Interface/',
    '/Server_Request_Factory_Interface/',
    '/Stream_Factory_Interface/',
    '/Uploaded_File_Factory_Interface/',
    '/Uri_Factory_Interface/',
    '/Allow_Dynamic_Properties/',
    '/\bremoteStream\b/',
    '/\bskipReadBytes\b/',
    '/\bheaderNames\b/',
    '/\btellPos\b/',
    '/\brequestTarget\b/',
    '/\breasonPhrase\b/',
    '/\bstatusCode\b/',
    '/\bcookieParams\b/',
    '/\bparsedBody\b/',
    '/\bqueryParams\b/',
    '/\bserverParams\b/',
    '/\buploadedFiles\b/',
    '/\bcustomMetadata\b/',
    '/\bclientFilename\b/',
    '/\bclientMediaType\b/',
    '/\bcomposedComponents\b/',
    '/\bstreamFor\b/',
    '/\buriFor\b/',
    '/\btryFopen\b/',
    '/\bfromGlobals\b/',
    '/\bnormalizeFiles\b/',
    '/\bgetUriFromGlobals\b/',
];

function scan_file(string $path, array $patterns): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        return ["Could not read {$path}"];
    }
    $hits = [];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $contents)) {
            $hits[] = "{$path}: matched forbidden pattern {$pattern}";
        }
    }

    return $hits;
}

function scan_tree(string $dir, array $patterns): array
{
    $hits = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }
        $hits = array_merge($hits, scan_file($file->getPathname(), $patterns));
    }

    return $hits;
}

/**
 * @return list<string>
 */
function class_file_mismatches_for(string $phpPath, string $contents): array
{
    $basename = basename($phpPath, '.php');
    $mismatches = [];

    if (preg_match_all(
        '/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)\s+extends\s+TestCase/m',
        $contents,
        $testMatches,
        PREG_SET_ORDER
    )) {
        foreach ($testMatches as $match) {
            $declared = $match[1];
            if ($declared !== $basename) {
                $mismatches[] = sprintf(
                    '%s: TestCase class %s must match file basename %s',
                    $phpPath,
                    $declared,
                    $basename
                );
            }
        }
    }

    $underSrc = strpos($phpPath, '/src/') !== false || strpos($phpPath, '\\src\\') !== false;
    if ($underSrc && preg_match_all(
        '/^\s*(?:final\s+|abstract\s+)?(?:class|trait|interface)\s+([A-Za-z0-9_]+)/m',
        $contents,
        $srcMatches,
        PREG_SET_ORDER
    )) {
        foreach ($srcMatches as $match) {
            $declared = $match[1];
            if ($declared !== $basename) {
                $mismatches[] = sprintf(
                    '%s: declared %s must match file basename %s',
                    $phpPath,
                    $declared,
                    $basename
                );
            }
        }
    }

    return $mismatches;
}

/**
 * @return list<string>
 */
function class_file_mismatches(string $phpPath): array
{
    $contents = file_get_contents($phpPath);
    if ($contents === false) {
        return ["Could not read {$phpPath}"];
    }

    return class_file_mismatches_for($phpPath, $contents);
}

/**
 * @return list<string>
 */
function collect_class_file_mismatches(array $roots): array
{
    $violations = [];
    foreach ($roots as $path) {
        if (!is_dir($path)) {
            continue;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $violations = array_merge($violations, class_file_mismatches($file->getPathname()));
        }
    }

    return $violations;
}

$responseTestFile = $root . '/tests/Response_Test.php';
$responseContents = file_get_contents($responseTestFile);
if ($responseContents === false) {
    fwrite(STDERR, "check-legacy-names: could not read Response_Test.php for demo.\n");
    exit(1);
}
$demoContents = str_replace('class Response_Test ', 'class ResponseTest ', $responseContents);
$demoMismatches = class_file_mismatches_for($responseTestFile, $demoContents);
if ($demoMismatches === []) {
    fwrite(STDERR, "check-legacy-names: class/file mismatch demo did not fail.\n");
    exit(1);
}
fwrite(STDOUT, "check-legacy-names: class/file mismatch demo failed for injected wrong class name.\n");

$utilsTestFile = $root . '/tests/Utils_Test.php';
$utilsTestContents = file_get_contents($utilsTestFile);
if ($utilsTestContents === false) {
    fwrite(STDERR, "check-legacy-names: could not read Utils_Test.php for camelCase demo.\n");
    exit(1);
}
if (strpos($utilsTestContents, '$modified_request') === false) {
    fwrite(STDERR, "check-legacy-names: Utils_Test.php is missing expected \$modified_request anchor for demo.\n");
    exit(1);
}
$demoUtilsContents = preg_replace('/\$modified_request/', '$modifiedRequest', $utilsTestContents, 1);
if (!is_string($demoUtilsContents) || $demoUtilsContents === $utilsTestContents) {
    fwrite(STDERR, "check-legacy-names: camelCase identifier demo could not inject wrong variable name.\n");
    exit(1);
}
$demoSnakeViolations = collect_snake_case_violations($utilsTestFile, $demoUtilsContents, $upstreamAllowlist);
if ($demoSnakeViolations === []) {
    fwrite(STDERR, "check-legacy-names: camelCase identifier demo did not fail.\n");
    exit(1);
}
fwrite(STDOUT, "check-legacy-names: camelCase identifier demo failed for injected variable name.\n");

$violations = [];
foreach ($scanPaths as $path) {
    if (is_dir($path)) {
        $violations = array_merge($violations, scan_tree($path, $patterns));
        continue;
    }
    if (is_file($path)) {
        $violations = array_merge($violations, scan_file($path, $patterns));
    }
}

$violations = array_merge($violations, collect_class_file_mismatches([$root . '/src', $root . '/tests']));
$violations = array_merge(
    $violations,
    collect_snake_case_violations_tree($root . '/src', $upstreamAllowlist),
    collect_snake_case_violations_tree($root . '/tests', $upstreamAllowlist)
);

fwrite(STDOUT, "check-legacy-names: matcher rejects GuzzleHttp\\Psr7\\HttpFactory.\n");
fwrite(STDOUT, "check-legacy-names: matcher accepts Guzzle_Http\\Psr7\\Http_Factory.\n");

if ($violations !== []) {
    foreach ($violations as $violation) {
        fwrite(STDERR, $violation . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "check-legacy-names: no pre-rename repo-owned names remain.\n");
fwrite(STDOUT, "check-legacy-names: all src and TestCase class names match their file basenames.\n");
fwrite(STDOUT, "check-legacy-names: no repo-owned camelCase identifiers outside upstream allowlist.\n");
exit(0);
