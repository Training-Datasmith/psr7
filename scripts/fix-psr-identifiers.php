#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$replacements = [
    'Psr\\Http\\Message\\Server_RequestInterface' => 'Psr\\Http\\Message\\ServerRequestInterface',
    'Psr\\Http\\Message\\Uploaded_FileInterface' => 'Psr\\Http\\Message\\UploadedFileInterface',
    'Psr\\Http\\Message\\Server_RequestFactoryInterface' => 'Psr\\Http\\Message\\ServerRequestFactoryInterface',
    'Psr\\Http\\Message\\Uploaded_FileFactoryInterface' => 'Psr\\Http\\Message\\UploadedFileFactoryInterface',
    'createUploaded_FileFromSpec' => 'createUploadedFileFromSpec',
    'createUploaded_File' => 'createUploadedFile',
    'createServer_Request' => 'createServerRequest',
    'getUploaded_Files' => 'getUploadedFiles',
    'withUploaded_Files' => 'withUploadedFiles',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root . '/src', FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $contents = file_get_contents($path);
    if ($contents === false) {
        continue;
    }
    $updated = str_replace(array_keys($replacements), array_values($replacements), $contents);
    if ($path === $root . '/src/Http_Factory.php') {
        $updated = preg_replace('/public function create_stream\(/', 'public function createStream(', $updated);
    }
    if ($updated !== $contents) {
        file_put_contents($path, $updated);
        fwrite(STDOUT, "Fixed {$path}\n");
    }
}
