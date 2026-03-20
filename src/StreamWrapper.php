<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Stream_Interface;
/**
 * Converts Guzzle streams into PHP stream resources.
 *
 * @see https://www.php.net/streamwrapper
 */
final class Stream_Wrapper
{
    /** @var resource */
    public $context;
    /** @var StreamInterface */
    private $stream;
    /** @var string r, r+, or w */
    private $mode;
    /**
     * Returns a resource representing the stream.
     *
     * @param StreamInterface $stream The stream to get a resource for
     *
     * @return resource
     *
     * @throws \InvalidArgumentException if stream is not readable or writable
     */
    public static function get_resource(Stream_Interface $stream)
    {
        self::register();
        if ($stream->is_readable()) {
            $mode = $stream->is_writable() ? 'r+' : 'r';
        } elseif ($stream->is_writable()) {
            $mode = 'w';
        } else {
            throw new \InvalidArgumentException('The stream must be readable, ' . 'writable, or both.');
        }
        return fopen('guzzle://stream', $mode, false, self::create_stream_context($stream));
    }
    /**
     * Creates a stream context that can be used to open a stream as a php stream resource.
     *
     * @return resource
     */
    public static function create_stream_context(Stream_Interface $stream)
    {
        return stream_context_create(['guzzle' => ['stream' => $stream]]);
    }
    /**
     * Registers the stream wrapper if needed
     */
    public static function register(): void
    {
        if (!in_array('guzzle', stream_get_wrappers())) {
            stream_wrapper_register('guzzle', self::class);
        }
    }
    public function stream_open(string $path, string $mode, int $options, ?string &$opened_path = null): bool
    {
        $options = stream_context_get_options($this->context);
        if (!isset($options['guzzle']['stream'])) {
            return false;
        }
        $this->mode = $mode;
        $this->stream = $options['guzzle']['stream'];
        return true;
    }
    public function stream_read(int $count): string
    {
        return $this->stream->read($count);
    }
    public function stream_write(string $data): int
    {
        return $this->stream->write($data);
    }
    public function stream_tell(): int
    {
        return $this->stream->tell();
    }
    public function stream_eof(): bool
    {
        return $this->stream->eof();
    }
    public function stream_seek(int $offset, int $whence): bool
    {
        $this->stream->seek($offset, $whence);
        return true;
    }
    /**
     * @return resource|false
     */
    public function stream_cast()
    {
        $stream = clone $this->stream;
        $resource = $stream->detach();
        return $resource ?? false;
    }
    /**
     * @return array{
     *   dev: int,
     *   ino: int,
     *   mode: int,
     *   nlink: int,
     *   uid: int,
     *   gid: int,
     *   rdev: int,
     *   size: int,
     *   atime: int,
     *   mtime: int,
     *   ctime: int,
     *   blksize: int,
     *   blocks: int
     * }|false
     */
    public function stream_stat()
    {
        if ($this->stream->get_size() === null) {
            return false;
        }
        static $mode_map = ['r' => 33060, 'rb' => 33060, 'r+' => 33206, 'w' => 33188, 'wb' => 33188];
        return ['dev' => 0, 'ino' => 0, 'mode' => $mode_map[$this->mode], 'nlink' => 0, 'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => $this->stream->get_size() ?: 0, 'atime' => 0, 'mtime' => 0, 'ctime' => 0, 'blksize' => 0, 'blocks' => 0];
    }
    /**
     * @return array{
     *   dev: int,
     *   ino: int,
     *   mode: int,
     *   nlink: int,
     *   uid: int,
     *   gid: int,
     *   rdev: int,
     *   size: int,
     *   atime: int,
     *   mtime: int,
     *   ctime: int,
     *   blksize: int,
     *   blocks: int
     * }
     */
    public function url_stat(): array
    {
        return ['dev' => 0, 'ino' => 0, 'mode' => 0, 'nlink' => 0, 'uid' => 0, 'gid' => 0, 'rdev' => 0, 'size' => 0, 'atime' => 0, 'mtime' => 0, 'ctime' => 0, 'blksize' => 0, 'blocks' => 0];
    }
}