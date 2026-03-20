<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Message_Interface;
use Psr\Http\Message\Request_Interface;
use Psr\Http\Message\Response_Interface;
final class Message
{
    /**
     * Returns the string representation of an HTTP message.
     *
     * @param MessageInterface $message Message to convert to a string.
     */
    public static function to_string(Message_Interface $message): string
    {
        if ($message instanceof Request_Interface) {
            $msg = trim($message->get_method() . ' ' . $message->get_request_target()) . ' HTTP/' . $message->get_protocol_version();
            if (!$message->has_header('host')) {
                $msg .= "\r\nHost: " . $message->get_uri()->get_host();
            }
        } elseif ($message instanceof Response_Interface) {
            $msg = 'HTTP/' . $message->get_protocol_version() . ' ' . $message->get_status_code() . ' ' . $message->get_reason_phrase();
        } else {
            throw new \InvalidArgumentException('Unknown message type');
        }
        foreach ($message->get_headers() as $name => $values) {
            if (is_string($name) && strtolower($name) === 'set-cookie') {
                foreach ($values as $value) {
                    $msg .= "\r\n{$name}: " . $value;
                }
            } else {
                $msg .= "\r\n{$name}: " . implode(', ', $values);
            }
        }
        return "{$msg}\r\n\r\n" . $message->get_body();
    }
    /**
     * Get a short summary of the message body.
     *
     * Will return `null` if the response is not printable.
     *
     * @param MessageInterface $message    The message to get the body summary
     * @param int              $truncateAt The maximum allowed size of the summary
     */
    public static function body_summary(Message_Interface $message, int $truncate_at = 120): ?string
    {
        $body = $message->get_body();
        if (!$body->is_seekable() || !$body->is_readable()) {
            return null;
        }
        $size = $body->get_size();
        if ($size === 0) {
            return null;
        }
        $body->rewind();
        $summary = $body->read($truncate_at);
        $body->rewind();
        if ($size > $truncate_at) {
            $summary .= ' (truncated...)';
        }
        // Matches any printable character, including unicode characters:
        // letters, marks, numbers, punctuation, spacing, and separators.
        if (preg_match('/[^\pL\pM\pN\pP\pS\pZ\n\r\t]/u', $summary) !== 0) {
            return null;
        }
        return $summary;
    }
    /**
     * Attempts to rewind a message body and throws an exception on failure.
     *
     * The body of the message will only be rewound if a call to `tell()`
     * returns a value other than `0`.
     *
     * @param MessageInterface $message Message to rewind
     *
     * @throws \RuntimeException
     */
    public static function rewind_body(Message_Interface $message): void
    {
        $body = $message->get_body();
        if ($body->tell()) {
            $body->rewind();
        }
    }
    /**
     * Parses an HTTP message into an associative array.
     *
     * The array contains the "start-line" key containing the start line of
     * the message, "headers" key containing an associative array of header
     * array values, and a "body" key containing the body of the message.
     *
     * @param string $message HTTP request or response to parse.
     */
    public static function parse_message(string $message): array
    {
        if (!$message) {
            throw new \InvalidArgumentException('Invalid message');
        }
        $message = ltrim($message, "\r\n");
        $message_parts = preg_split("/\r?\n\r?\n/", $message, 2);
        if ($message_parts === false || count($message_parts) !== 2) {
            throw new \InvalidArgumentException('Invalid message: Missing header delimiter');
        }
        [$raw_headers, $body] = $message_parts;
        $raw_headers .= "\r\n";
        // Put back the delimiter we split previously
        $header_parts = preg_split("/\r?\n/", $raw_headers, 2);
        if ($header_parts === false || count($header_parts) !== 2) {
            throw new \InvalidArgumentException('Invalid message: Missing status line');
        }
        [$start_line, $raw_headers] = $header_parts;
        if (preg_match("/(?:^HTTP\\/|^[A-Z]+ \\S+ HTTP\\/)(\\d+(?:\\.\\d+)?)/i", $start_line, $matches) && $matches[1] === '1.0') {
            // Header folding is deprecated for HTTP/1.1, but allowed in HTTP/1.0
            $raw_headers = preg_replace(Rfc7230::HEADER_FOLD_REGEX, ' ', $raw_headers);
        }
        /** @var array[] $headerLines */
        $count = preg_match_all(Rfc7230::HEADER_REGEX, $raw_headers, $header_lines, PREG_SET_ORDER);
        // If these aren't the same, then one line didn't match and there's an invalid header.
        if ($count !== substr_count($raw_headers, "\n")) {
            // Folding is deprecated, see https://datatracker.ietf.org/doc/html/rfc7230#section-3.2.4
            if (preg_match(Rfc7230::HEADER_FOLD_REGEX, $raw_headers)) {
                throw new \InvalidArgumentException('Invalid header syntax: Obsolete line folding');
            }
            throw new \InvalidArgumentException('Invalid header syntax');
        }
        $headers = [];
        foreach ($header_lines as $header_line) {
            $headers[$header_line[1]][] = $header_line[2];
        }
        return ['start-line' => $start_line, 'headers' => $headers, 'body' => $body];
    }
    /**
     * Constructs a URI for an HTTP request message.
     *
     * @param string $path    Path from the start-line
     * @param array  $headers Array of headers (each value an array).
     */
    public static function parse_request_uri(string $path, array $headers): string
    {
        $host_key = array_filter(array_keys($headers), function ($k): bool {
            // Numeric array keys are converted to int by PHP.
            $k = (string) $k;
            return strtolower($k) === 'host';
        });
        // If no host is found, then a full URI cannot be constructed.
        if (!$host_key) {
            return $path;
        }
        $host = $headers[reset($host_key)][0];
        $scheme = substr($host, -4) === ':443' ? 'https' : 'http';
        return $scheme . '://' . $host . '/' . ltrim($path, '/');
    }
    /**
     * Parses a request message string into a request object.
     *
     * @param string $message Request message string.
     */
    public static function parse_request(string $message): Request_Interface
    {
        $data = self::parse_message($message);
        $matches = [];
        if (!preg_match('/^[\S]+\s+([a-zA-Z]+:\/\/|\/).*/', $data['start-line'], $matches)) {
            throw new \InvalidArgumentException('Invalid request string');
        }
        $parts = explode(' ', $data['start-line'], 3);
        $version = isset($parts[2]) ? explode('/', $parts[2])[1] : '1.1';
        $request = new Request($parts[0], $matches[1] === '/' ? self::parse_request_uri($parts[1], $data['headers']) : $parts[1], $data['headers'], $data['body'], $version);
        return $matches[1] === '/' ? $request : $request->with_request_target($parts[1]);
    }
    /**
     * Parses a response message string into a response object.
     *
     * @param string $message Response message string.
     */
    public static function parse_response(string $message): Response_Interface
    {
        $data = self::parse_message($message);
        // According to https://datatracker.ietf.org/doc/html/rfc7230#section-3.1.2
        // the space between status-code and reason-phrase is required. But
        // browsers accept responses without space and reason as well.
        if (!preg_match('/^HTTP\/.* [0-9]{3}( .*|$)/', $data['start-line'])) {
            throw new \InvalidArgumentException('Invalid response string: ' . $data['start-line']);
        }
        $parts = explode(' ', $data['start-line'], 3);
        return new Response((int) $parts[1], $data['headers'], $data['body'], explode('/', $parts[0])[1], $parts[2] ?? null);
    }
}