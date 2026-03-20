<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Response_Interface;
use Psr\Http\Message\Stream_Interface;
/**
 * PSR-7 response implementation.
 */
class Response implements Response_Interface
{
    use Message_Trait;
    /** Map of standard HTTP status code/reason phrases */
    private const PHRASES = [100 => 'Continue', 101 => 'Switching Protocols', 102 => 'Processing', 200 => 'OK', 201 => 'Created', 202 => 'Accepted', 203 => 'Non-Authoritative Information', 204 => 'No Content', 205 => 'Reset Content', 206 => 'Partial Content', 207 => 'Multi-status', 208 => 'Already Reported', 300 => 'Multiple Choices', 301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified', 305 => 'Use Proxy', 306 => 'Switch Proxy', 307 => 'Temporary Redirect', 308 => 'Permanent Redirect', 400 => 'Bad Request', 401 => 'Unauthorized', 402 => 'Payment Required', 403 => 'Forbidden', 404 => 'Not Found', 405 => 'Method Not Allowed', 406 => 'Not Acceptable', 407 => 'Proxy Authentication Required', 408 => 'Request Time-out', 409 => 'Conflict', 410 => 'Gone', 411 => 'Length Required', 412 => 'Precondition Failed', 413 => 'Request Entity Too Large', 414 => 'Request-URI Too Large', 415 => 'Unsupported Media Type', 416 => 'Requested range not satisfiable', 417 => 'Expectation Failed', 418 => 'I\'m a teapot', 422 => 'Unprocessable Entity', 423 => 'Locked', 424 => 'Failed Dependency', 425 => 'Unordered Collection', 426 => 'Upgrade Required', 428 => 'Precondition Required', 429 => 'Too Many Requests', 431 => 'Request Header Fields Too Large', 451 => 'Unavailable For Legal Reasons', 500 => 'Internal Server Error', 501 => 'Not Implemented', 502 => 'Bad Gateway', 503 => 'Service Unavailable', 504 => 'Gateway Time-out', 505 => 'HTTP Version not supported', 506 => 'Variant Also Negotiates', 507 => 'Insufficient Storage', 508 => 'Loop Detected', 510 => 'Not Extended', 511 => 'Network Authentication Required'];
    /** @var string */
    private $reason_phrase;
    /** @var int */
    private $status_code;
    /**
     * @param int                                  $status  Status code
     * @param (string|string[])[]                  $headers Response headers
     * @param string|resource|StreamInterface|null $body    Response body
     * @param string                               $version Protocol version
     * @param string|null                          $reason  Reason phrase (when empty a default will be used based on the status code)
     */
    public function __construct(int $status = 200, array $headers = [], $body = null, string $version = '1.1', ?string $reason = null)
    {
        $this->assert_status_code_range($status);
        $this->status_code = $status;
        if ($body !== '' && $body !== null) {
            $this->stream = Utils::stream_for($body);
        }
        $this->set_headers($headers);
        if ($reason == '' && isset(self::PHRASES[$this->status_code])) {
            $this->reason_phrase = self::PHRASES[$this->status_code];
        } else {
            $this->reason_phrase = (string) $reason;
        }
        $this->protocol = $version;
    }
    public function get_status_code(): int
    {
        return $this->status_code;
    }
    public function get_reason_phrase(): string
    {
        return $this->reason_phrase;
    }
    public function with_status($code, $reason_phrase = ''): Response_Interface
    {
        $this->assert_status_code_is_integer($code);
        $code = (int) $code;
        $this->assert_status_code_range($code);
        $new = clone $this;
        $new->status_code = $code;
        if ($reason_phrase == '' && isset(self::PHRASES[$new->status_code])) {
            $reason_phrase = self::PHRASES[$new->status_code];
        }
        $new->reason_phrase = (string) $reason_phrase;
        return $new;
    }
    /**
     * @param mixed $statusCode
     */
    private function assert_status_code_is_integer($status_code): void
    {
        if (filter_var($status_code, FILTER_VALIDATE_INT) === false) {
            throw new \InvalidArgumentException('Status code must be an integer value.');
        }
    }
    private function assert_status_code_range(int $status_code): void
    {
        if ($status_code < 100 || $status_code >= 600) {
            throw new \InvalidArgumentException('Status code must be an integer value between 1xx and 5xx.');
        }
    }
}