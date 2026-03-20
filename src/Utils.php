<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Request_Interface;
use Psr\Http\Message\Server_Request_Interface;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uri_Interface;
final class Utils
{
    /**
     * Remove the items given by the keys, case insensitively from the data.
     *
     * @param (string|int)[] $keys
     */
    public static function caseless_remove(array $keys, array $data): array
    {
        $result = [];
        foreach ($keys as &$key) {
            $key = strtolower((string) $key);
        }
        foreach ($data as $k => $v) {
            if (!in_array(strtolower((string) $k), $keys)) {
                $result[$k] = $v;
            }
        }
        return $result;
    }
    /**
     * Copy the contents of a stream into another stream until the given number
     * of bytes have been read.
     *
     * @param StreamInterface $source Stream to read from
     * @param StreamInterface $dest   Stream to write to
     * @param int             $maxLen Maximum number of bytes to read. Pass -1
     *                                to read the entire stream.
     *
     * @throws \RuntimeException on error.
     */
    public static function copy_to_stream(Stream_Interface $source, Stream_Interface $dest, int $max_len = -1): void
    {
        $buffer_size = 8192;
        if ($max_len === -1) {
            while (!$source->eof()) {
                if (!$dest->write($source->read($buffer_size))) {
                    break;
                }
            }
        } else {
            $remaining = $max_len;
            while ($remaining > 0 && !$source->eof()) {
                $buf = $source->read(min($buffer_size, $remaining));
                $len = strlen($buf);
                if (!$len) {
                    break;
                }
                $remaining -= $len;
                $dest->write($buf);
            }
        }
    }
    /**
     * Copy the contents of a stream into a string until the given number of
     * bytes have been read.
     *
     * @param StreamInterface $stream Stream to read
     * @param int             $maxLen Maximum number of bytes to read. Pass -1
     *                                to read the entire stream.
     *
     * @throws \RuntimeException on error.
     */
    public static function copy_to_string(Stream_Interface $stream, int $max_len = -1): string
    {
        $buffer = '';
        if ($max_len === -1) {
            while (!$stream->eof()) {
                $buf = $stream->read(1048576);
                if ($buf === '') {
                    break;
                }
                $buffer .= $buf;
            }
            return $buffer;
        }
        $len = 0;
        while (!$stream->eof() && $len < $max_len) {
            $buf = $stream->read($max_len - $len);
            if ($buf === '') {
                break;
            }
            $buffer .= $buf;
            $len = strlen($buffer);
        }
        return $buffer;
    }
    /**
     * Calculate a hash of a stream.
     *
     * This method reads the entire stream to calculate a rolling hash, based
     * on PHP's `hash_init` functions.
     *
     * @param StreamInterface $stream    Stream to calculate the hash for
     * @param string          $algo      Hash algorithm (e.g. md5, crc32, etc)
     * @param bool            $rawOutput Whether or not to use raw output
     *
     * @throws \RuntimeException on error.
     */
    public static function hash(Stream_Interface $stream, string $algo, bool $raw_output = false): string
    {
        $pos = $stream->tell();
        if ($pos > 0) {
            $stream->rewind();
        }
        $ctx = hash_init($algo);
        while (!$stream->eof()) {
            hash_update($ctx, $stream->read(1048576));
        }
        $out = hash_final($ctx, $raw_output);
        $stream->seek($pos);
        return $out;
    }
    /**
     * Clone and modify a request with the given changes.
     *
     * This method is useful for reducing the number of clones needed to mutate
     * a message.
     *
     * The changes can be one of:
     * - method: (string) Changes the HTTP method.
     * - set_headers: (array) Sets the given headers.
     * - remove_headers: (array) Remove the given headers.
     * - body: (mixed) Sets the given body.
     * - uri: (UriInterface) Set the URI.
     * - query: (string) Set the query string value of the URI.
     * - version: (string) Set the protocol version.
     *
     * @param RequestInterface $request Request to clone and modify.
     * @param array            $changes Changes to apply.
     */
    public static function modify_request(Request_Interface $request, array $changes): Request_Interface
    {
        if (!$changes) {
            return $request;
        }
        $headers = $request->get_headers();
        if (!isset($changes['uri'])) {
            $uri = $request->get_uri();
        } else {
            // Remove the host header if one is on the URI
            if ($host = $changes['uri']->get_host()) {
                $changes['set_headers']['Host'] = $host;
                if ($port = $changes['uri']->get_port()) {
                    $standard_ports = ['http' => 80, 'https' => 443];
                    $scheme = $changes['uri']->get_scheme();
                    if (isset($standard_ports[$scheme]) && $port != $standard_ports[$scheme]) {
                        $changes['set_headers']['Host'] .= ':' . $port;
                    }
                }
            }
            $uri = $changes['uri'];
        }
        if (!empty($changes['remove_headers'])) {
            $headers = self::caseless_remove($changes['remove_headers'], $headers);
        }
        if (!empty($changes['set_headers'])) {
            $headers = self::caseless_remove(array_keys($changes['set_headers']), $headers);
            $headers = $changes['set_headers'] + $headers;
        }
        if (isset($changes['query'])) {
            $uri = $uri->with_query($changes['query']);
        }
        if ($request instanceof Server_Request_Interface) {
            $new = (new Server_Request($changes['method'] ?? $request->get_method(), $uri, $headers, $changes['body'] ?? $request->get_body(), $changes['version'] ?? $request->get_protocol_version(), $request->get_server_params()))->with_parsed_body($request->get_parsed_body())->with_query_params($request->get_query_params())->with_cookie_params($request->get_cookie_params())->with_uploaded_files($request->get_uploaded_files());
            foreach ($request->get_attributes() as $key => $value) {
                $new = $new->with_attribute($key, $value);
            }
            return $new;
        }
        return new Request($changes['method'] ?? $request->get_method(), $uri, $headers, $changes['body'] ?? $request->get_body(), $changes['version'] ?? $request->get_protocol_version());
    }
    /**
     * Read a line from the stream up to the maximum allowed buffer length.
     *
     * @param StreamInterface $stream    Stream to read from
     * @param int|null        $maxLength Maximum buffer length
     */
    public static function read_line(Stream_Interface $stream, ?int $max_length = null): string
    {
        $buffer = '';
        $size = 0;
        while (!$stream->eof()) {
            if ('' === $byte = $stream->read(1)) {
                return $buffer;
            }
            $buffer .= $byte;
            // Break when a new line is found or the max length - 1 is reached
            if ($byte === "\n" || ++$size === $max_length - 1) {
                break;
            }
        }
        return $buffer;
    }
    /**
     * Redact the password in the user info part of a URI.
     */
    public static function redact_user_info(Uri_Interface $uri): Uri_Interface
    {
        $user_info = $uri->get_user_info();
        if (false !== $pos = \strpos($user_info, ':')) {
            return $uri->with_user_info(\substr($user_info, 0, $pos), '***');
        }
        return $uri;
    }
    /**
     * Create a new stream based on the input type.
     *
     * Options is an associative array that can contain the following keys:
     * - metadata: Array of custom metadata.
     * - size: Size of the stream.
     *
     * This method accepts the following `$resource` types:
     * - `Psr\Http\Message\StreamInterface`: Returns the value as-is.
     * - `string`: Creates a stream object that uses the given string as the contents.
     * - `resource`: Creates a stream object that wraps the given PHP stream resource.
     * - `Iterator`: If the provided value implements `Iterator`, then a read-only
     *   stream object will be created that wraps the given iterable. Each time the
     *   stream is read from, data from the iterator will fill a buffer and will be
     *   continuously called until the buffer is equal to the requested read size.
     *   Subsequent read calls will first read from the buffer and then call `next`
     *   on the underlying iterator until it is exhausted.
     * - `object` with `__toString()`: If the object has the `__toString()` method,
     *   the object will be cast to a string and then a stream will be returned that
     *   uses the string value.
     * - `NULL`: When `null` is passed, an empty stream object is returned.
     * - `callable` When a callable is passed, a read-only stream object will be
     *   created that invokes the given callable. The callable is invoked with the
     *   number of suggested bytes to read. The callable can return any number of
     *   bytes, but MUST return `false` when there is no more data to return. The
     *   stream object that wraps the callable will invoke the callable until the
     *   number of requested bytes are available. Any additional bytes will be
     *   buffered and used in subsequent reads.
     *
     * @param resource|string|int|float|bool|StreamInterface|callable|\Iterator|null $resource Entity body data
     * @param array{size?: int, metadata?: array}                                    $options  Additional options
     *
     * @throws \InvalidArgumentException if the $resource arg is not valid.
     */
    public static function stream_for($resource = '', array $options = []): Stream_Interface
    {
        if (is_scalar($resource)) {
            $stream = self::try_fopen('php://temp', 'r+');
            if ($resource !== '') {
                fwrite($stream, (string) $resource);
                fseek($stream, 0);
            }
            return new Stream($stream, $options);
        }
        switch (gettype($resource)) {
            case 'resource':
                /*
                 * The 'php://input' is a special stream with quirks and inconsistencies.
                 * We avoid using that stream by reading it into php://temp
                 */
                /** @var resource $resource */
                if ((\stream_get_meta_data($resource)['uri'] ?? '') === 'php://input') {
                    $stream = self::try_fopen('php://temp', 'w+');
                    stream_copy_to_stream($resource, $stream);
                    fseek($stream, 0);
                    $resource = $stream;
                }
                return new Stream($resource, $options);
            case 'object':
                /** @var object $resource */
                if ($resource instanceof Stream_Interface) {
                    return $resource;
                }
                if ($resource instanceof \Iterator) {
                    return new Pump_Stream(function () use ($resource) {
                        if (!$resource->valid()) {
                            return false;
                        }
                        $result = $resource->current();
                        $resource->next();
                        return $result;
                    }, $options);
                }
                /** @var object $resource */
                if (method_exists($resource, '__toString')) {
                    return self::stream_for((string) $resource, $options);
                }
                break;
            case 'NULL':
                return new Stream(self::try_fopen('php://temp', 'r+'), $options);
        }
        if (is_callable($resource)) {
            return new Pump_Stream($resource, $options);
        }
        throw new \InvalidArgumentException('Invalid resource type: ' . gettype($resource));
    }
    /**
     * Safely opens a PHP stream resource using a filename.
     *
     * When fopen fails, PHP normally raises a warning. This function adds an
     * error handler that checks for errors and throws an exception instead.
     *
     * @param string $filename File to open
     * @param string $mode     Mode used to open the file
     *
     * @return resource
     *
     * @throws \RuntimeException if the file cannot be opened
     */
    public static function try_fopen(string $filename, string $mode)
    {
        $ex = null;
        set_error_handler(static function (int $errno, string $errstr) use ($filename, $mode, &$ex): bool {
            $ex = new \RuntimeException(sprintf('Unable to open "%s" using mode "%s": %s', $filename, $mode, $errstr));
            return true;
        });
        try {
            /** @var resource $handle */
            $handle = fopen($filename, $mode);
        } catch (\Throwable $e) {
            $ex = new \RuntimeException(sprintf('Unable to open "%s" using mode "%s": %s', $filename, $mode, $e->get_message()), 0, $e);
        }
        restore_error_handler();
        if ($ex) {
            /** @var \RuntimeException $ex */
            throw $ex;
        }
        return $handle;
    }
    /**
     * Safely gets the contents of a given stream.
     *
     * When stream_get_contents fails, PHP normally raises a warning. This
     * function adds an error handler that checks for errors and throws an
     * exception instead.
     *
     * @param resource $stream
     *
     * @throws \RuntimeException if the stream cannot be read
     */
    public static function try_get_contents($stream): string
    {
        $ex = null;
        set_error_handler(static function (int $errno, string $errstr) use (&$ex): bool {
            $ex = new \RuntimeException(sprintf('Unable to read stream contents: %s', $errstr));
            return true;
        });
        try {
            /** @var string|false $contents */
            $contents = stream_get_contents($stream);
            if ($contents === false) {
                $ex = new \RuntimeException('Unable to read stream contents');
            }
        } catch (\Throwable $e) {
            $ex = new \RuntimeException(sprintf('Unable to read stream contents: %s', $e->get_message()), 0, $e);
        }
        restore_error_handler();
        if ($ex) {
            /** @var \RuntimeException $ex */
            throw $ex;
        }
        return $contents;
    }
    /**
     * Returns a UriInterface for the given value.
     *
     * This function accepts a string or UriInterface and returns a
     * UriInterface for the given value. If the value is already a
     * UriInterface, it is returned as-is.
     *
     * @param string|UriInterface $uri
     *
     * @throws \InvalidArgumentException
     */
    public static function uri_for($uri): Uri_Interface
    {
        if ($uri instanceof Uri_Interface) {
            return $uri;
        }
        if (is_string($uri)) {
            return new Uri($uri);
        }
        throw new \InvalidArgumentException('URI must be a string or UriInterface');
    }
}