<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Message_Interface;
use Psr\Http\Message\Stream_Interface;
/**
 * Trait implementing functionality common to requests and responses.
 */
trait Message_Trait
{
    /** @var string[][] Map of all registered headers, as original name => array of values */
    private $headers = [];
    /** @var string[] Map of lowercase header name => original name at registration */
    private $header_names = [];
    /** @var string */
    private $protocol = '1.1';
    /** @var StreamInterface|null */
    private $stream;
    public function get_protocol_version(): string
    {
        return $this->protocol;
    }
    /**
     * @return static
     */
    public function with_protocol_version($version): Message_Interface
    {
        if ($this->protocol === $version) {
            return $this;
        }
        $new = clone $this;
        $new->protocol = $version;
        return $new;
    }
    public function get_headers(): array
    {
        return $this->headers;
    }
    public function has_header($header): bool
    {
        return isset($this->header_names[strtolower($header)]);
    }
    public function get_header($header): array
    {
        $header = strtolower($header);
        if (!isset($this->header_names[$header])) {
            return [];
        }
        $header = $this->header_names[$header];
        return $this->headers[$header];
    }
    public function get_header_line($header): string
    {
        return implode(', ', $this->get_header($header));
    }
    /**
     * @return static
     */
    public function with_header($header, $value): Message_Interface
    {
        $this->assert_header($header);
        $value = $this->normalize_header_value($value);
        $normalized = strtolower($header);
        $new = clone $this;
        if (isset($new->header_names[$normalized])) {
            unset($new->headers[$new->header_names[$normalized]]);
        }
        $new->header_names[$normalized] = $header;
        $new->headers[$header] = $value;
        return $new;
    }
    /**
     * @return static
     */
    public function with_added_header($header, $value): Message_Interface
    {
        $this->assert_header($header);
        $value = $this->normalize_header_value($value);
        $normalized = strtolower($header);
        $new = clone $this;
        if (isset($new->header_names[$normalized])) {
            $header = $this->header_names[$normalized];
            $new->headers[$header] = array_merge($this->headers[$header], $value);
        } else {
            $new->header_names[$normalized] = $header;
            $new->headers[$header] = $value;
        }
        return $new;
    }
    /**
     * @return static
     */
    public function without_header($header): Message_Interface
    {
        $normalized = strtolower($header);
        if (!isset($this->header_names[$normalized])) {
            return $this;
        }
        $header = $this->header_names[$normalized];
        $new = clone $this;
        unset($new->headers[$header], $new->header_names[$normalized]);
        return $new;
    }
    public function get_body(): Stream_Interface
    {
        if (!$this->stream) {
            $this->stream = Utils::stream_for('');
        }
        return $this->stream;
    }
    /**
     * @return static
     */
    public function with_body(Stream_Interface $body): Message_Interface
    {
        if ($body === $this->stream) {
            return $this;
        }
        $new = clone $this;
        $new->stream = $body;
        return $new;
    }
    /**
     * @param (string|string[])[] $headers
     */
    private function set_headers(array $headers): void
    {
        $this->header_names = $this->headers = [];
        foreach ($headers as $header => $value) {
            // Numeric array keys are converted to int by PHP.
            $header = (string) $header;
            $this->assert_header($header);
            $value = $this->normalize_header_value($value);
            $normalized = strtolower($header);
            if (isset($this->header_names[$normalized])) {
                $header = $this->header_names[$normalized];
                $this->headers[$header] = array_merge($this->headers[$header], $value);
            } else {
                $this->header_names[$normalized] = $header;
                $this->headers[$header] = $value;
            }
        }
    }
    /**
     * @param mixed $value
     *
     * @return string[]
     */
    private function normalize_header_value($value): array
    {
        if (!is_array($value)) {
            return $this->trim_and_validate_header_values([$value]);
        }
        return $this->trim_and_validate_header_values($value);
    }
    /**
     * Trims whitespace from the header values.
     *
     * Spaces and tabs ought to be excluded by parsers when extracting the field value from a header field.
     *
     * header-field = field-name ":" OWS field-value OWS
     * OWS          = *( SP / HTAB )
     *
     * @param mixed[] $values Header values
     *
     * @return string[] Trimmed header values
     *
     * @see https://datatracker.ietf.org/doc/html/rfc7230#section-3.2.4
     */
    private function trim_and_validate_header_values(array $values): array
    {
        return array_map(function ($value): string {
            if (!is_scalar($value) && null !== $value) {
                throw new \InvalidArgumentException(sprintf('Header value must be scalar or null but %s provided.', is_object($value) ? get_class($value) : gettype($value)));
            }
            $trimmed = trim((string) $value, " \t");
            $this->assert_value($trimmed);
            return $trimmed;
        }, array_values($values));
    }
    /**
     * @see https://datatracker.ietf.org/doc/html/rfc7230#section-3.2
     *
     * @param mixed $header
     */
    private function assert_header($header): void
    {
        if (!is_string($header)) {
            throw new \InvalidArgumentException(sprintf('Header name must be a string but %s provided.', is_object($header) ? get_class($header) : gettype($header)));
        }
        if (!preg_match('/^[a-zA-Z0-9\'`#$%&*+.^_|~!-]+$/D', $header)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not valid header name.', $header));
        }
    }
    /**
     * @see https://datatracker.ietf.org/doc/html/rfc7230#section-3.2
     *
     * field-value    = *( field-content / obs-fold )
     * field-content  = field-vchar [ 1*( SP / HTAB ) field-vchar ]
     * field-vchar    = VCHAR / obs-text
     * VCHAR          = %x21-7E
     * obs-text       = %x80-FF
     * obs-fold       = CRLF 1*( SP / HTAB )
     */
    private function assert_value(string $value): void
    {
        // The regular expression intentionally does not support the obs-fold production, because as
        // per RFC 7230#3.2.4:
        //
        // A sender MUST NOT generate a message that includes
        // line folding (i.e., that has any field-value that contains a match to
        // the obs-fold rule) unless the message is intended for packaging
        // within the message/http media type.
        //
        // Clients must not send a request with line folding and a server sending folded headers is
        // likely very rare. Line folding is a fairly obscure feature of HTTP/1.1 and thus not accepting
        // folding is not likely to break any legitimate use case.
        if (!preg_match('/^[\x20\x09\x21-\x7E\x80-\xFF]*$/D', $value)) {
            throw new \InvalidArgumentException(sprintf('"%s" is not valid header value.', $value));
        }
    }
}