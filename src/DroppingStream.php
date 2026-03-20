<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Stream_Interface;
/**
 * Stream decorator that begins dropping data once the size of the underlying
 * stream becomes too full.
 */
final class Dropping_Stream implements Stream_Interface
{
    use Stream_Decorator_Trait;
    /** @var int */
    private $max_length;
    /** @var StreamInterface */
    private $stream;
    /**
     * @param StreamInterface $stream    Underlying stream to decorate.
     * @param int             $maxLength Maximum size before dropping data.
     */
    public function __construct(Stream_Interface $stream, int $max_length)
    {
        $this->stream = $stream;
        $this->max_length = $max_length;
    }
    public function write($string): int
    {
        $diff = $this->max_length - $this->stream->get_size();
        // Begin returning 0 when the underlying stream is too large.
        if ($diff <= 0) {
            return 0;
        }
        // Write the stream or a subset of the stream if needed.
        if (strlen($string) < $diff) {
            return $this->stream->write($string);
        }
        return $this->stream->write(substr($string, 0, $diff));
    }
}