<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Stream_Interface;
/**
 * Stream decorator that prevents a stream from being seeked.
 */
final class No_Seek_Stream implements Stream_Interface
{
    use Stream_Decorator_Trait;
    /** @var StreamInterface */
    private $stream;
    public function seek($offset, $whence = SEEK_SET): void
    {
        throw new \RuntimeException('Cannot seek a NoSeekStream');
    }
    public function is_seekable(): bool
    {
        return false;
    }
}