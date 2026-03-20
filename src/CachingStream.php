<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Stream_Interface;
/**
 * Stream decorator that can cache previously read bytes from a sequentially
 * read stream.
 */
final class Caching_Stream implements Stream_Interface
{
    use Stream_Decorator_Trait;
    /** @var StreamInterface Stream being wrapped */
    private $remote_stream;
    /** @var int Number of bytes to skip reading due to a write on the buffer */
    private $skip_read_bytes = 0;
    /**
     * @var StreamInterface
     */
    private $stream;
    /**
     * We will treat the buffer object as the body of the stream
     *
     * @param StreamInterface $stream Stream to cache. The cursor is assumed to be at the beginning of the stream.
     * @param StreamInterface $target Optionally specify where data is cached
     */
    public function __construct(Stream_Interface $stream, ?Stream_Interface $target = null)
    {
        $this->remote_stream = $stream;
        $this->stream = $target ?: new Stream(Utils::try_fopen('php://temp', 'r+'));
    }
    public function get_size(): ?int
    {
        $remote_size = $this->remote_stream->get_size();
        if (null === $remote_size) {
            return null;
        }
        return max($this->stream->get_size(), $remote_size);
    }
    public function rewind(): void
    {
        $this->seek(0);
    }
    public function seek($offset, $whence = SEEK_SET): void
    {
        if ($whence === SEEK_SET) {
            $byte = $offset;
        } elseif ($whence === SEEK_CUR) {
            $byte = $offset + $this->tell();
        } elseif ($whence === SEEK_END) {
            $size = $this->remote_stream->get_size();
            if ($size === null) {
                $size = $this->cache_entire_stream();
            }
            $byte = $size + $offset;
        } else {
            throw new \InvalidArgumentException('Invalid whence');
        }
        $diff = $byte - $this->stream->get_size();
        if ($diff > 0) {
            // Read the remoteStream until we have read in at least the amount
            // of bytes requested, or we reach the end of the file.
            while ($diff > 0 && !$this->remote_stream->eof()) {
                $this->read($diff);
                $diff = $byte - $this->stream->get_size();
            }
        } else {
            // We can just do a normal seek since we've already seen this byte.
            $this->stream->seek($byte);
        }
    }
    public function read($length): string
    {
        // Perform a regular read on any previously read data from the buffer
        $data = $this->stream->read($length);
        $remaining = $length - strlen($data);
        // More data was requested so read from the remote stream
        if ($remaining) {
            // If data was written to the buffer in a position that would have
            // been filled from the remote stream, then we must skip bytes on
            // the remote stream to emulate overwriting bytes from that
            // position. This mimics the behavior of other PHP stream wrappers.
            $remote_data = $this->remote_stream->read($remaining + $this->skip_read_bytes);
            if ($this->skip_read_bytes) {
                $len = strlen($remote_data);
                $remote_data = substr($remote_data, $this->skip_read_bytes);
                $this->skip_read_bytes = max(0, $this->skip_read_bytes - $len);
            }
            $data .= $remote_data;
            $this->stream->write($remote_data);
        }
        return $data;
    }
    public function write($string): int
    {
        // When appending to the end of the currently read stream, you'll want
        // to skip bytes from being read from the remote stream to emulate
        // other stream wrappers. Basically replacing bytes of data of a fixed
        // length.
        $overflow = strlen($string) + $this->tell() - $this->remote_stream->tell();
        if ($overflow > 0) {
            $this->skip_read_bytes += $overflow;
        }
        return $this->stream->write($string);
    }
    public function eof(): bool
    {
        return $this->stream->eof() && $this->remote_stream->eof();
    }
    /**
     * Close both the remote stream and buffer stream
     */
    public function close(): void
    {
        $this->remote_stream->close();
        $this->stream->close();
    }
    private function cache_entire_stream(): int
    {
        $target = new Fn_Stream(['write' => 'strlen']);
        Utils::copy_to_stream($this, $target);
        return $this->tell();
    }
}