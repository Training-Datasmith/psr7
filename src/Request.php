<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\Request_Interface;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uri_Interface;
/**
 * PSR-7 request implementation.
 */
class Request implements Request_Interface
{
    use Message_Trait;
    /** @var string */
    private $method;
    /** @var string|null */
    private $request_target;
    /** @var UriInterface */
    private $uri;
    /**
     * @param string                               $method  HTTP method
     * @param string|UriInterface                  $uri     URI
     * @param (string|string[])[]                  $headers Request headers
     * @param string|resource|StreamInterface|null $body    Request body
     * @param string                               $version Protocol version
     */
    public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1')
    {
        $this->assert_method($method);
        if (!$uri instanceof Uri_Interface) {
            $uri = new Uri($uri);
        }
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->set_headers($headers);
        $this->protocol = $version;
        if (!isset($this->header_names['host'])) {
            $this->update_host_from_uri();
        }
        if ($body !== '' && $body !== null) {
            $this->stream = Utils::stream_for($body);
        }
    }
    public function get_request_target(): string
    {
        if ($this->request_target !== null) {
            return $this->request_target;
        }
        $target = $this->uri->get_path();
        if ($target === '') {
            $target = '/';
        }
        if ($this->uri->get_query() != '') {
            $target .= '?' . $this->uri->get_query();
        }
        return $target;
    }
    public function with_request_target($request_target): Request_Interface
    {
        if (preg_match('#\s#', $request_target)) {
            throw new InvalidArgumentException('Invalid request target provided; cannot contain whitespace');
        }
        $new = clone $this;
        $new->request_target = $request_target;
        return $new;
    }
    public function get_method(): string
    {
        return $this->method;
    }
    public function with_method($method): Request_Interface
    {
        $this->assert_method($method);
        $new = clone $this;
        $new->method = strtoupper($method);
        return $new;
    }
    public function get_uri(): Uri_Interface
    {
        return $this->uri;
    }
    public function with_uri(Uri_Interface $uri, $preserve_host = false): Request_Interface
    {
        if ($uri === $this->uri) {
            return $this;
        }
        $new = clone $this;
        $new->uri = $uri;
        if (!$preserve_host || !isset($this->header_names['host'])) {
            $new->update_host_from_uri();
        }
        return $new;
    }
    private function update_host_from_uri(): void
    {
        $host = $this->uri->get_host();
        if ($host == '') {
            return;
        }
        if (($port = $this->uri->get_port()) !== null) {
            $host .= ':' . $port;
        }
        if (isset($this->header_names['host'])) {
            $header = $this->header_names['host'];
        } else {
            $header = 'Host';
            $this->header_names['host'] = 'Host';
        }
        // Ensure Host is the first header.
        // See: https://datatracker.ietf.org/doc/html/rfc7230#section-5.4
        $this->headers = [$header => [$host]] + $this->headers;
    }
    /**
     * @param mixed $method
     */
    private function assert_method($method): void
    {
        if (!is_string($method) || $method === '') {
            throw new InvalidArgumentException('Method must be a non-empty string.');
        }
    }
}