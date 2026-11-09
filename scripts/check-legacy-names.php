<?php

declare(strict_types=1);

$root = dirname(__DIR__);

/** @return array<string, true> */
function load_phpunit_hook_allowlist(string $path): array
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

/**
 * @return array<string, true>
 */
function load_psr_interface_method_names(): array
{
    $names = [];
    $interfaces = [
        \Psr\Http\Message\MessageInterface::class,
        \Psr\Http\Message\RequestInterface::class,
        \Psr\Http\Message\ResponseInterface::class,
        \Psr\Http\Message\ServerRequestInterface::class,
        \Psr\Http\Message\StreamInterface::class,
        \Psr\Http\Message\UploadedFileInterface::class,
        \Psr\Http\Message\UriInterface::class,
        \Psr\Http\Message\RequestFactoryInterface::class,
        \Psr\Http\Message\ResponseFactoryInterface::class,
        \Psr\Http\Message\ServerRequestFactoryInterface::class,
        \Psr\Http\Message\StreamFactoryInterface::class,
        \Psr\Http\Message\UploadedFileFactoryInterface::class,
        \Psr\Http\Message\UriFactoryInterface::class,
    ];

    foreach ($interfaces as $interface) {
        if (!interface_exists($interface)) {
            continue;
        }
        $ref = new ReflectionClass($interface);
        foreach ($ref->getMethods() as $method) {
            $names[$method->getName()] = true;
        }
    }

    return $names;
}

/**
 * @return array<string, true> keys like "Message_Trait::getHeaders"
 */
function build_scoped_upstream_method_allowlist(string $root): array
{
    $autoload = $root . '/vendor/autoload.php';
    if (!is_readable($autoload)) {
        fwrite(STDERR, "check-legacy-names: run composer update first (missing vendor/autoload.php).\n");
        exit(1);
    }
    require_once $autoload;

    $psrMethods = load_psr_interface_method_names();
    $scoped = [];
    foreach ([$root . '/src', $root . '/tests'] as $scanDir) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($scanDir, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            if ($code === false) {
                continue;
            }
            if (!preg_match('/namespace\s+([^;]+);/', $code, $nsMatch)) {
                continue;
            }
            $namespace = trim($nsMatch[1]);
            $typeName = extract_type_name($code);
            if ($typeName === null) {
                continue;
            }
            $fqcn = $namespace . '\\' . $typeName;
            if (!class_exists($fqcn) && !trait_exists($fqcn)) {
                continue;
            }
            $ref = new ReflectionClass($fqcn);
            foreach ($ref->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $fqcn) {
                    continue;
                }
                $methodName = $method->getName();
                if (!isset($psrMethods[$methodName])) {
                    if ($methodName === 'jsonSerialize' && $ref->implementsInterface(\JsonSerializable::class)) {
                        $scoped[$typeName . '::' . $methodName] = true;
                    }
                    continue;
                }
                if ($ref->isTrait()) {
                    $scoped[$typeName . '::' . $methodName] = true;
                    continue;
                }
                foreach ($ref->getInterfaces() as $interface) {
                    if ($interface->hasMethod($methodName)) {
                        $scoped[$typeName . '::' . $methodName] = true;
                        break;
                    }
                }
            }
        }
    }

    return $scoped;
}

/**
 * @return array<string, list<string>>
 */
function load_parameter_allowlist(string $path): array
{
    $map = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $parts = explode('::', $line);
        if (count($parts) !== 3) {
            fwrite(STDERR, "check-legacy-names: invalid parameter allowlist entry: {$line}\n");
            exit(1);
        }
        [$class, $method, $parameter] = $parts;
        $key = $class . '::' . $method;
        if (!isset($map[$key])) {
            $map[$key] = [];
        }
        $map[$key][] = $parameter;
    }

    return $map;
}

function extract_class_name(string $code): ?string
{
    if (preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z0-9_]+)/m', $code, $matches) !== 1) {
        return null;
    }

    return $matches[1];
}

function extract_type_name(string $code): ?string
{
    if (preg_match('/^\s*trait\s+([A-Za-z0-9_]+)/m', $code, $matches) === 1) {
        return $matches[1];
    }

    return extract_class_name($code);
}

function extends_test_case(string $code): bool
{
    return (bool) preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+[A-Za-z0-9_]+\s+extends\s+TestCase\b/m', $code);
}

function is_snake_case_test_method_name(string $name): bool
{
    return (bool) preg_match('/^test_[a-z0-9_]+$/', $name);
}

/**
 * @param array<string, true> $scopedMethodAllowlist
 * @param array<string, true> $phpunitHooks
 */
function is_allowed_camelcase_method_declaration(
    string $methodName,
    bool $underSrc,
    bool $underTests,
    ?string $typeName,
    bool $extendsTestCase,
    array $scopedMethodAllowlist,
    array $phpunitHooks
): bool {
    if (!$underSrc && !$underTests) {
        return true;
    }
    if ($underSrc && $typeName !== null) {
        return isset($scopedMethodAllowlist[$typeName . '::' . $methodName]);
    }
    if ($underTests) {
        if (strpos($methodName, 'test') === 0) {
            return is_snake_case_test_method_name($methodName);
        }
        if ($extendsTestCase && isset($phpunitHooks[$methodName])) {
            return true;
        }
        if ($typeName !== null && isset($scopedMethodAllowlist[$typeName . '::' . $methodName])) {
            return true;
        }

        return false;
    }

    return false;
}

function is_repo_camelcase_identifier(string $name): bool
{
  if ($name === '' || !ctype_lower($name[0])) {
    return false;
  }

  return (bool) preg_match('/[a-z][A-Z]/', $name);
}

/**
 * @param array<string, true> $scopedMethodAllowlist
 * @param array<string, true> $phpunitHooks
 * @param array<string, list<string>> $parameterAllowlist
 * @return list<string>
 */
function collect_snake_case_violations(
    string $filePath,
    string $code,
    array $scopedMethodAllowlist,
    array $phpunitHooks,
    array $parameterAllowlist
): array {
    $violations = [];
    $tokens = token_get_all($code);
    $expectFunctionName = false;
    $underSrc = strpos($filePath, '/src/') !== false;
    $underTests = strpos($filePath, '/tests/') !== false;
    $typeName = extract_type_name($code);
    $extendsTestCase = $underTests && extends_test_case($code);
    $currentFunction = null;
    /** @var array<string, true> */
    $scopedParameters = [];

    foreach ($tokens as $token) {
        if (!is_array($token)) {
            if ($token === '{') {
                // keep scopedParameters for function body
            }
            if ($token === '(') {
                $expectFunctionName = false;
            }
            continue;
        }

        [$id, $text] = $token;

        if ($id === T_FUNCTION) {
            $expectFunctionName = true;
            $currentFunction = null;
            $scopedParameters = [];
            continue;
        }

        if ($expectFunctionName && $id === T_STRING) {
            $currentFunction = $text;
            if (is_repo_camelcase_identifier($text) && !is_allowed_camelcase_method_declaration(
                $text,
                $underSrc,
                $underTests,
                $typeName,
                $extendsTestCase,
                $scopedMethodAllowlist,
                $phpunitHooks
            )) {
                $violations[] = sprintf('%s: repo-owned camelCase identifier %s', $filePath, $text);
            }
            if ($typeName !== null) {
                $scopeKey = $typeName . '::' . $currentFunction;
                $scopedParameters = [];
                foreach ($parameterAllowlist[$scopeKey] ?? [] as $parameter) {
                    $scopedParameters[$parameter] = true;
                }
            }
            $expectFunctionName = false;
            continue;
        }

        if ($expectFunctionName && $id !== T_WHITESPACE && $id !== T_COMMENT && $id !== T_DOC_COMMENT) {
            $expectFunctionName = false;
        }

        if ($id === T_VARIABLE) {
            $name = substr($text, 1);
            if (!is_repo_camelcase_identifier($name)) {
                continue;
            }
            if (isset($scopedParameters[$name])) {
                continue;
            }
            $violations[] = sprintf('%s: repo-owned camelCase identifier $%s', $filePath, $name);
        }
    }

    return $violations;
}

/**
 * @param array<string, true> $scopedMethodAllowlist
 * @param array<string, true> $phpunitHooks
 * @param array<string, list<string>> $parameterAllowlist
 * @return list<string>
 */
function collect_snake_case_violations_tree(
    string $dir,
    array $scopedMethodAllowlist,
    array $phpunitHooks,
    array $parameterAllowlist
): array
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
        $violations = array_merge(
            $violations,
            collect_snake_case_violations(
                $file->getPathname(),
                $contents,
                $scopedMethodAllowlist,
                $phpunitHooks,
                $parameterAllowlist
            )
        );
    }

    return $violations;
}

$phpunitHooks = load_phpunit_hook_allowlist($root . '/scripts/upstream-identifier-allowlist.txt');
$scopedMethodAllowlist = build_scoped_upstream_method_allowlist($root);
$parameterAllowlist = load_parameter_allowlist($root . '/scripts/upstream-parameter-allowlist.txt');

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
$demoSnakeViolations = collect_snake_case_violations(
    $utilsTestFile,
    $demoUtilsContents,
    $scopedMethodAllowlist,
    $phpunitHooks,
    $parameterAllowlist
);
if ($demoSnakeViolations === []) {
    fwrite(STDERR, "check-legacy-names: camelCase identifier demo did not fail.\n");
    exit(1);
}
fwrite(STDOUT, "check-legacy-names: camelCase identifier demo failed for injected variable name.\n");

$uriResolverFile = $root . '/src/Uri_Resolver.php';
$uriResolverContents = file_get_contents($uriResolverFile);
if ($uriResolverContents === false) {
    fwrite(STDERR, "check-legacy-names: could not read Uri_Resolver.php for contextual demo.\n");
    exit(1);
}
$demoResolverContents = preg_replace(
    '/public static function resolve\(UriInterface \$base, UriInterface \$rel\): UriInterface\s*\{/',
    "public static function resolve(UriInterface \$base, UriInterface \$rel): UriInterface\n    {\n        \$targetPath = null;",
    $uriResolverContents,
    1
);
if (!is_string($demoResolverContents) || $demoResolverContents === $uriResolverContents) {
    fwrite(STDERR, "check-legacy-names: contextual targetPath demo could not inject wrong variable name.\n");
    exit(1);
}
$demoTargetPathViolations = collect_snake_case_violations(
    $uriResolverFile,
    $demoResolverContents,
    $scopedMethodAllowlist,
    $phpunitHooks,
    $parameterAllowlist
);
if ($demoTargetPathViolations === []) {
    fwrite(STDERR, "check-legacy-names: contextual targetPath demo did not fail.\n");
    exit(1);
}
fwrite(STDOUT, "check-legacy-names: contextual targetPath demo failed for injected repo-owned variable.\n");

$multipartFile = $root . '/src/Multipart_Stream.php';
$multipartContents = file_get_contents($multipartFile);
if ($multipartContents === false) {
    fwrite(STDERR, "check-legacy-names: could not read Multipart_Stream.php for method demo.\n");
    exit(1);
}
$demoMultipartContents = str_replace('private function get_headers', 'private function getHeaders', $multipartContents, $count);
if ($count !== 1) {
    fwrite(STDERR, "check-legacy-names: Multipart_Stream getHeaders demo could not inject wrong method name.\n");
    exit(1);
}
$demoMultipartViolations = collect_snake_case_violations(
    $multipartFile,
    $demoMultipartContents,
    $scopedMethodAllowlist,
    $phpunitHooks,
    $parameterAllowlist
);
if ($demoMultipartViolations === []) {
    fwrite(STDERR, "check-legacy-names: Multipart_Stream getHeaders demo did not fail.\n");
    exit(1);
}
fwrite(STDOUT, "check-legacy-names: scoped method demo failed for injected Multipart_Stream::getHeaders.\n");

$responseTestFile = $root . '/tests/Response_Test.php';
$responseTestContents = file_get_contents($responseTestFile);
if ($responseTestContents === false) {
    fwrite(STDERR, "check-legacy-names: could not read Response_Test.php for testFoo demo.\n");
    exit(1);
}
$demoTestFooContents = preg_replace(
    '/public function test_default_constructor\(\): void/',
    'public function testFoo(): void',
    $responseTestContents,
    1
);
if (!is_string($demoTestFooContents) || $demoTestFooContents === $responseTestContents) {
    fwrite(STDERR, "check-legacy-names: testFoo demo could not inject wrong test method name.\n");
    exit(1);
}
$demoTestFooViolations = collect_snake_case_violations(
    $responseTestFile,
    $demoTestFooContents,
    $scopedMethodAllowlist,
    $phpunitHooks,
    $parameterAllowlist
);
if ($demoTestFooViolations === []) {
    fwrite(STDERR, "check-legacy-names: testFoo demo did not fail.\n");
    exit(1);
}
fwrite(STDOUT, "check-legacy-names: test method demo failed for injected testFoo.\n");

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
    collect_snake_case_violations_tree($root . '/src', $scopedMethodAllowlist, $phpunitHooks, $parameterAllowlist),
    collect_snake_case_violations_tree($root . '/tests', $scopedMethodAllowlist, $phpunitHooks, $parameterAllowlist)
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
fwrite(STDOUT, "check-legacy-names: no repo-owned camelCase identifiers outside scoped upstream allowlists.\n");
exit(0);
