<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Request_Factory_Interface;
use Psr\Http\Message\Request_Interface;
use Psr\Http\Message\Response_Factory_Interface;
use Psr\Http\Message\Response_Interface;
use Psr\Http\Message\Server_Request_Factory_Interface;
use Psr\Http\Message\Server_Request_Interface;
use Psr\Http\Message\Stream_Factory_Interface;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uploaded_File_Factory_Interface;
use Psr\Http\Message\Uploaded_File_Interface;
use Psr\Http\Message\Uri_Factory_Interface;
use Psr\Http\Message\Uri_Interface;
/**
 * Implements all of the PSR-17 interfaces.
 *
 * Note: in consuming code it is recommended to require the implemented interfaces
 * and inject the instance of this class multiple times.
 */
final class Http_Factory implements Request_Factory_Interface, Response_Factory_Interface, Server_Request_Factory_Interface, Stream_Factory_Interface, Uploaded_File_Factory_Interface, Uri_Factory_Interface
{
    public function create_uploaded_file(Stream_Interface $stream, ?int $size = null, int $error = \UPLOAD_ERR_OK, ?string $client_filename = null, ?string $client_media_type = null): Uploaded_File_Interface
    {
        if ($size === null) {
            $size = $stream->get_size();
        }
        return new Uploaded_File($stream, $size, $error, $client_filename, $client_media_type);
    }
    public function create_stream(string $content = ''): Stream_Interface
    {
        return Utils::stream_for($content);
    }
    public function create_stream_from_file(string $file, string $mode = 'r'): Stream_Interface
    {
        try {
            $resource = Utils::try_fopen($file, $mode);
        } catch (\RuntimeException $e) {
            if ('' === $mode || false === \in_array($mode[0], ['r', 'w', 'a', 'x', 'c'], true)) {
                throw new \InvalidArgumentException(sprintf('Invalid file opening mode "%s"', $mode), 0, $e);
            }
            throw $e;
        }
        return Utils::stream_for($resource);
    }
    public function create_stream_from_resource($resource): Stream_Interface
    {
        return Utils::stream_for($resource);
    }
    public function create_server_request(string $method, $uri, array $server_params = []): Server_Request_Interface
    {
        if (empty($method)) {
            if (!empty($server_params['REQUEST_METHOD'])) {
                $method = $server_params['REQUEST_METHOD'];
            } else {
                throw new \InvalidArgumentException('Cannot determine HTTP method');
            }
        }
        return new Server_Request($method, $uri, [], null, '1.1', $server_params);
    }
    public function create_response(int $code = 200, string $reason_phrase = ''): Response_Interface
    {
        return new Response($code, [], null, '1.1', $reason_phrase);
    }
    public function create_request(string $method, $uri): Request_Interface
    {
        return new Request($method, $uri);
    }
    public function create_uri(string $uri = ''): Uri_Interface
    {
        return new Uri($uri);
    }
}