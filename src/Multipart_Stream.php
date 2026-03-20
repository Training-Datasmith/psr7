<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Stream_Interface;
/**
 * Stream that when read returns bytes for a streaming multipart or
 * multipart/form-data stream.
 */
final class Multipart_Stream implements Stream_Interface
{
    use Stream_Decorator_Trait;
    /** @var string */
    private $boundary;
    /** @var StreamInterface */
    private $stream;
    /**
     * @param array  $elements Array of associative arrays, each containing a
     *                         required "name" key mapping to the form field,
     *                         name, a required "contents" key mapping to any
     *                         value accepted by Utils::streamFor() (scalar,
     *                         null, resource, StreamInterface, Iterator, or
     *                         callable), or an array for nested expansion.
     *                         Optional keys include "headers" (associative
     *                         array of custom headers) and "filename" (string
     *                         to send as the filename in the part).
     *                         When "contents" is an array, it is recursively
     *                         expanded into multiple fields using bracket notation
     *                         (e.g., name[0][key]). Empty arrays produce no fields.
     *                         The "filename" and "headers" options cannot be used
     *                         with array contents.
     * @param string $boundary You can optionally provide a specific boundary
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(array $elements = [], ?string $boundary = null)
    {
        $this->boundary = $boundary ?: bin2hex(random_bytes(20));
        $this->stream = $this->create_stream($elements);
    }
    public function get_boundary(): string
    {
        return $this->boundary;
    }
    public function is_writable(): bool
    {
        return false;
    }
    /**
     * Get the headers needed before transferring the content of a POST file
     *
     * @param string[] $headers
     */
    private function get_headers(array $headers): string
    {
        $str = '';
        foreach ($headers as $key => $value) {
            $str .= "{$key}: {$value}\r\n";
        }
        return "--{$this->boundary}\r\n" . trim($str) . "\r\n\r\n";
    }
    /**
     * Create the aggregate stream that will be used to upload the POST data
     */
    protected function create_stream(array $elements = []): Stream_Interface
    {
        $stream = new Append_Stream();
        foreach ($elements as $element) {
            if (!is_array($element)) {
                throw new \UnexpectedValueException('An array is expected');
            }
            $this->add_element($stream, $element);
        }
        // Add the trailing boundary with CRLF
        $stream->add_stream(Utils::stream_for("--{$this->boundary}--\r\n"));
        return $stream;
    }
    private function add_element(Append_Stream $stream, array $element): void
    {
        foreach (['contents', 'name'] as $key) {
            if (!array_key_exists($key, $element)) {
                throw new \InvalidArgumentException("A '{$key}' key is required");
            }
        }
        if (!is_string($element['name']) && !is_int($element['name'])) {
            throw new \InvalidArgumentException("The 'name' key must be a string or integer");
        }
        if (is_array($element['contents'])) {
            if (array_key_exists('filename', $element) || array_key_exists('headers', $element)) {
                throw new \InvalidArgumentException("The 'filename' and 'headers' options cannot be used when 'contents' is an array");
            }
            $this->add_nested_elements($stream, $element['contents'], (string) $element['name']);
            return;
        }
        $element['contents'] = Utils::stream_for($element['contents']);
        if (empty($element['filename'])) {
            $uri = $element['contents']->get_metadata('uri');
            if ($uri && \is_string($uri) && \substr($uri, 0, 6) !== 'php://' && \substr($uri, 0, 7) !== 'data://') {
                $element['filename'] = $uri;
            }
        }
        [$body, $headers] = $this->create_element((string) $element['name'], $element['contents'], $element['filename'] ?? null, $element['headers'] ?? []);
        $stream->add_stream(Utils::stream_for($this->get_headers($headers)));
        $stream->add_stream($body);
        $stream->add_stream(Utils::stream_for("\r\n"));
    }
    /**
     * Recursively expand array contents into multiple form fields.
     *
     * @param array<array-key, mixed> $contents
     */
    private function add_nested_elements(Append_Stream $stream, array $contents, string $root): void
    {
        foreach ($contents as $key => $value) {
            $field_name = $root === '' ? sprintf('[%s]', (string) $key) : sprintf('%s[%s]', $root, (string) $key);
            if (is_array($value)) {
                $this->add_nested_elements($stream, $value, $field_name);
            } else {
                $this->add_element($stream, ['name' => $field_name, 'contents' => $value]);
            }
        }
    }
    /**
     * @param string[] $headers
     *
     * @return array{0: StreamInterface, 1: string[]}
     */
    private function create_element(string $name, Stream_Interface $stream, ?string $filename, array $headers): array
    {
        // Set a default content-disposition header if one was no provided
        $disposition = self::get_header($headers, 'content-disposition');
        if (!$disposition) {
            $headers['Content-Disposition'] = $filename === '0' || $filename ? sprintf('form-data; name="%s"; filename="%s"', $name, basename($filename)) : "form-data; name=\"{$name}\"";
        }
        // Set a default content-length header if one was no provided
        $length = self::get_header($headers, 'content-length');
        if (!$length) {
            if ($length = $stream->get_size()) {
                $headers['Content-Length'] = (string) $length;
            }
        }
        // Set a default Content-Type if one was not supplied
        $type = self::get_header($headers, 'content-type');
        if (!$type && ($filename === '0' || $filename)) {
            $headers['Content-Type'] = Mime_Type::from_filename($filename) ?? 'application/octet-stream';
        }
        return [$stream, $headers];
    }
    /**
     * @param string[] $headers
     */
    private static function get_header(array $headers, string $key): ?string
    {
        $lowercase_header = strtolower($key);
        foreach ($headers as $k => $v) {
            if (strtolower((string) $k) === $lowercase_header) {
                return $v;
            }
        }
        return null;
    }
}