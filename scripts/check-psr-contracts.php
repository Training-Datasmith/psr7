<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Guzzle_Http\Psr7\Http_Factory;
use Guzzle_Http\Psr7\Request;
use Guzzle_Http\Psr7\Response;
use Guzzle_Http\Psr7\Server_Request;
use Guzzle_Http\Psr7\Stream;
use Guzzle_Http\Psr7\Uploaded_File;
use Guzzle_Http\Psr7\Uri;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;

$failed = false;
$assert = static function (bool $ok, string $message) use (&$failed): void {
    if (!$ok) {
        fwrite(STDERR, $message . "\n");
        $failed = true;
    }
};

$assert(is_subclass_of(Http_Factory::class, RequestFactoryInterface::class), 'Http_Factory must implement RequestFactoryInterface');
$assert(is_subclass_of(Http_Factory::class, ResponseFactoryInterface::class), 'Http_Factory must implement ResponseFactoryInterface');
$assert(is_subclass_of(Http_Factory::class, ServerRequestFactoryInterface::class), 'Http_Factory must implement ServerRequestFactoryInterface');
$assert(is_subclass_of(Http_Factory::class, StreamFactoryInterface::class), 'Http_Factory must implement StreamFactoryInterface');
$assert(is_subclass_of(Http_Factory::class, UploadedFileFactoryInterface::class), 'Http_Factory must implement UploadedFileFactoryInterface');
$assert(is_subclass_of(Http_Factory::class, UriFactoryInterface::class), 'Http_Factory must implement UriFactoryInterface');

$assert(is_subclass_of(Request::class, RequestInterface::class), 'Request must implement RequestInterface');
$assert(is_subclass_of(Response::class, ResponseInterface::class), 'Response must implement ResponseInterface');
$assert(is_subclass_of(Server_Request::class, ServerRequestInterface::class), 'Server_Request must implement ServerRequestInterface');
$assert(is_subclass_of(Stream::class, StreamInterface::class), 'Stream must implement StreamInterface');
$assert(is_subclass_of(Uploaded_File::class, UploadedFileInterface::class), 'Uploaded_File must implement UploadedFileInterface');
$assert(is_subclass_of(Uri::class, UriInterface::class), 'Uri must implement UriInterface');

$factory = new Http_Factory();
$assert($factory->createRequest('GET', '/') instanceof RequestInterface, 'createRequest must return RequestInterface');
$assert($factory->createResponse() instanceof ResponseInterface, 'createResponse must return ResponseInterface');
$assert($factory->createServerRequest('GET', '/') instanceof ServerRequestInterface, 'createServerRequest must return ServerRequestInterface');
$assert($factory->createStream() instanceof StreamInterface, 'createStream must return StreamInterface');
$assert($factory->createUri() instanceof UriInterface, 'createUri must return UriInterface');

if ($failed) {
    exit(1);
}

fwrite(STDOUT, "check-psr-contracts: implementations match pinned PSR interfaces.\n");
exit(0);
