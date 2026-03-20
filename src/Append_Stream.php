<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Stream_Interface;
/**
 * Reads from multiple streams, one after the other.
 *
 * This is a read-only stream decorator.
 */
final class Append_Stream implements Stream_Interface
{
    /** @var StreamInterface[] Streams being decorated */
    private $streams = [];
    /** @var bool */
    private $seekable = true;
    /** @var int */
    private $current = 0;
    /** @var int */
    private $pos = 0;
    /**
     * @param StreamInterface[] $streams Streams to decorate. Each stream must
     *                                   be readable.
     */
    public function __construct(array $streams = [])
    {
        foreach ($streams as $stream) {
            $this->add_stream($stream);
        }
    }
    public function __toString(): string
    {
        try {
            $this->rewind();
            return $this->get_contents();
        } catch (\Throwable $e) {
            if (\PHP_VERSION_ID >= 70400) {
                throw $e;
            }
            trigger_error(sprintf('%s::__toString exception: %s', self::class, (string) $e), E_USER_ERROR);
            return '';
        }
    }
    /**
     * Add a stream to the AppendStream
     *
     * @param StreamInterface $stream Stream to append. Must be readable.
     *
     * @throws \InvalidArgumentException if the stream is not readable
     */
    public function add_stream(Stream_Interface $stream): void
    {
        if (!$stream->is_readable()) {
            throw new \InvalidArgumentException('Each stream must be readable');
        }
        // The stream is only seekable if all streams are seekable
        if (!$stream->is_seekable()) {
            $this->seekable = false;
        }
        $this->streams[] = $stream;
    }
    public function get_contents(): string
    {
        return Utils::copy_to_string($this);
    }
    /**
     * Closes each attached stream.
     */
    public function close(): void
    {
        $this->pos = $this->current = 0;
        $this->seekable = true;
        foreach ($this->streams as $stream) {
            $stream->close();
        }
        $this->streams = [];
    }
    /**
     * Detaches each attached stream.
     *
     * Returns null as it's not clear which underlying stream resource to return.
     */
    public function detach()
    {
        $this->pos = $this->current = 0;
        $this->seekable = true;
        foreach ($this->streams as $stream) {
            $stream->detach();
        }
        $this->streams = [];
        return null;
    }
    public function tell(): int
    {
        return $this->pos;
    }
    /**
     * Tries to calculate the size by adding the size of each stream.
     *
     * If any of the streams do not return a valid number, then the size of the
     * append stream cannot be determined and null is returned.
     */
    public function get_size(): ?int
    {
        $size = 0;
        foreach ($this->streams as $stream) {
            $s = $stream->get_size();
            if ($s === null) {
                return null;
            }
            $size += $s;
        }
        return $size;
    }
    public function eof(): bool
    {
        return !$this->streams || $this->current >= count($this->streams) - 1 && $this->streams[$this->current]->eof();
    }
    public function rewind(): void
    {
        $this->seek(0);
    }
    /**
     * Attempts to seek to the given position. Only supports SEEK_SET.
     */
    public function seek($offset, $whence = SEEK_SET): void
    {
        if (!$this->seekable) {
            throw new \RuntimeException('This AppendStream is not seekable');
        }
        if ($whence !== SEEK_SET) {
            throw new \RuntimeException('The AppendStream can only seek with SEEK_SET');
        }
        $this->pos = $this->current = 0;
        // Rewind each stream
        foreach ($this->streams as $i => $stream) {
            try {
                $stream->rewind();
            } catch (\Exception $e) {
                throw new \RuntimeException('Unable to seek stream ' . $i . ' of the AppendStream', 0, $e);
            }
        }
        // Seek to the actual position by reading from each stream
        while ($this->pos < $offset && !$this->eof()) {
            $result = $this->read(min(8096, $offset - $this->pos));
            if ($result === '') {
                break;
            }
        }
    }
    /**
     * Reads from all of the appended streams until the length is met or EOF.
     */
    public function read($length): string
    {
        $buffer = '';
        $total = count($this->streams) - 1;
        $remaining = $length;
        $progress_to_next = false;
        while ($remaining > 0) {
            // Progress to the next stream if needed.
            if ($progress_to_next || $this->streams[$this->current]->eof()) {
                $progress_to_next = false;
                if ($this->current === $total) {
                    break;
                }
                ++$this->current;
            }
            $result = $this->streams[$this->current]->read($remaining);
            if ($result === '') {
                $progress_to_next = true;
                continue;
            }
            $buffer .= $result;
            $remaining = $length - strlen($buffer);
        }
        $this->pos += strlen($buffer);
        return $buffer;
    }
    public function is_readable(): bool
    {
        return true;
    }
    public function is_writable(): bool
    {
        return false;
    }
    public function is_seekable(): bool
    {
        return $this->seekable;
    }
    public function write($string): int
    {
        throw new \RuntimeException('Cannot write to an AppendStream');
    }
    public function get_metadata($key = null): ?array
    {
        return $key ? null : [];
    }
}