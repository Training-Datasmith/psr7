<?php

declare(strict_types=1);

$root = dirname(__DIR__);

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
    '/\bServerRequestTest\b/',
    '/\bStreamTest\b/',
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

fwrite(STDOUT, "check-legacy-names: matcher rejects GuzzleHttp\\Psr7\\HttpFactory.\n");
fwrite(STDOUT, "check-legacy-names: matcher accepts Guzzle_Http\\Psr7\\Http_Factory.\n");

if ($violations !== []) {
    foreach ($violations as $violation) {
        fwrite(STDERR, $violation . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "check-legacy-names: no pre-rename repo-owned names remain.\n");
exit(0);
