<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Stream_Interface;
/**
 * Stream decorator trait
 *
 * @property StreamInterface $stream
 */
trait Stream_Decorator_Trait
{
    /**
     * @param StreamInterface $stream Stream to decorate
     */
    public function __construct(Stream_Interface $stream)
    {
        $this->stream = $stream;
    }
    /**
     * Magic method used to create a new stream if streams are not added in
     * the constructor of a decorator (e.g., LazyOpenStream).
     *
     * @return StreamInterface
     */
    public function __get(string $name)
    {
        if ($name === 'stream') {
            $this->stream = $this->create_stream();
            return $this->stream;
        }
        throw new \UnexpectedValueException("{$name} not found on class");
    }
    public function __toString(): string
    {
        try {
            if ($this->is_seekable()) {
                $this->seek(0);
            }
            return $this->get_contents();
        } catch (\Throwable $e) {
            if (\PHP_VERSION_ID >= 70400) {
                throw $e;
            }
            trigger_error(sprintf('%s::__toString exception: %s', self::class, (string) $e), E_USER_ERROR);
            return '';
        }
    }
    public function get_contents(): string
    {
        return Utils::copy_to_string($this);
    }
    /**
     * Allow decorators to implement custom methods
     *
     * @return mixed
     */
    public function __call(string $method, array $args)
    {
        /** @var callable $callable */
        $callable = [$this->stream, $method];
        $result = $callable(...$args);
        // Always return the wrapped object if the result is a return $this
        return $result === $this->stream ? $this : $result;
    }
    public function close(): void
    {
        $this->stream->close();
    }
    /**
     * @return mixed
     */
    public function get_metadata($key = null)
    {
        return $this->stream->get_metadata($key);
    }
    public function detach()
    {
        return $this->stream->detach();
    }
    public function get_size(): ?int
    {
        return $this->stream->get_size();
    }
    public function eof(): bool
    {
        return $this->stream->eof();
    }
    public function tell(): int
    {
        return $this->stream->tell();
    }
    public function is_readable(): bool
    {
        return $this->stream->is_readable();
    }
    public function is_writable(): bool
    {
        return $this->stream->is_writable();
    }
    public function is_seekable(): bool
    {
        return $this->stream->is_seekable();
    }
    public function rewind(): void
    {
        $this->seek(0);
    }
    public function seek($offset, $whence = SEEK_SET): void
    {
        $this->stream->seek($offset, $whence);
    }
    public function read($length): string
    {
        return $this->stream->read($length);
    }
    public function write($string): int
    {
        return $this->stream->write($string);
    }
    /**
     * Implement in subclasses to dynamically create streams when requested.
     *
     * @throws \BadMethodCallException
     */
    protected function create_stream(): Stream_Interface
    {
        throw new \BadMethodCallException('Not implemented');
    }
}