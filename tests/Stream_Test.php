<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7\Fn_Stream;
use Guzzle_Http\Psr7\Stream;
use Guzzle_Http\Psr7\Stream_Wrapper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Guzzle_Http\Psr7\Stream
 */
class Stream_Test extends TestCase
{
    public static $is_fread_error = false;

    public function test_constructor_throws_exception_on_invalid_argument(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Stream(true);
    }

    public function test_constructor_initializes_properties(): void
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
        self::assertSame('php://temp', $stream->getMetadata('uri'));
        self::assertIsArray($stream->getMetadata());
        self::assertSame(4, $stream->getSize());
        self::assertFalse($stream->eof());
        $stream->close();
    }

    public function test_constructor_initializes_properties_with_rb_plus(): void
    {
        $handle = fopen('php://temp', 'rb+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertTrue($stream->isReadable());
        self::assertTrue($stream->isWritable());
        self::assertTrue($stream->isSeekable());
        self::assertSame('php://temp', $stream->getMetadata('uri'));
        self::assertIsArray($stream->getMetadata());
        self::assertSame(4, $stream->getSize());
        self::assertFalse($stream->eof());
        $stream->close();
    }

    public function test_stream_closes_handle_on_destruct(): void
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        unset($stream);
        self::assertFalse(is_resource($handle));
    }

    public function test_converts_to_string(): void
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertSame('data', (string) $stream);
        self::assertSame('data', (string) $stream);
        $stream->close();
    }

    public function test_converts_to_string_non_seekable_stream(): void
    {
        $handle = popen('echo foo', 'r');
        $stream = new Stream($handle);
        self::assertFalse($stream->isSeekable());
        self::assertSame('foo', trim((string) $stream));
    }

    public function test_converts_to_string_non_seekable_partially_read_stream(): void
    {
        $handle = popen('echo bar', 'r');
        $stream = new Stream($handle);
        $first_letter = $stream->read(1);
        self::assertFalse($stream->isSeekable());
        self::assertSame('b', $first_letter);
        self::assertSame('ar', trim((string) $stream));
    }

    public function test_gets_contents(): void
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertSame('', $stream->getContents());
        $stream->seek(0);
        self::assertSame('data', $stream->getContents());
        self::assertSame('', $stream->getContents());
        $stream->close();
    }

    public function test_checks_eof(): void
    {
        $handle = fopen('php://temp', 'w+');
        fwrite($handle, 'data');
        $stream = new Stream($handle);
        self::assertSame(4, $stream->tell(), 'Stream cursor already at the end');
        self::assertFalse($stream->eof(), 'Stream still not eof');
        self::assertSame('', $stream->read(1), 'Need to read one more byte to reach eof');
        self::assertTrue($stream->eof());
        $stream->close();
    }

    public function test_get_size(): void
    {
        $size = filesize(__FILE__);
        $handle = fopen(__FILE__, 'r');
        $stream = new Stream($handle);
        self::assertSame($size, $stream->getSize());
        // Load from cache
        self::assertSame($size, $stream->getSize());
        $stream->close();
    }

    public function test_ensures_size_is_consistent(): void
    {
        $h = fopen('php://temp', 'w+');
        self::assertSame(3, fwrite($h, 'foo'));
        $stream = new Stream($h);
        self::assertSame(3, $stream->getSize());
        self::assertSame(4, $stream->write('test'));
        self::assertSame(7, $stream->getSize());
        self::assertSame(7, $stream->getSize());
        $stream->close();
    }

    public function test_provides_stream_position(): void
    {
        $handle = fopen('php://temp', 'w+');
        $stream = new Stream($handle);
        self::assertSame(0, $stream->tell());
        $stream->write('foo');
        self::assertSame(3, $stream->tell());
        $stream->seek(1);
        self::assertSame(1, $stream->tell());
        self::assertSame(ftell($handle), $stream->tell());
        $stream->close();
    }

    public function test_detach_stream_and_clear_properties(): void
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        self::assertSame($handle, $stream->detach());
        self::assertIsResource($handle, 'Stream is not closed');
        self::assertNull($stream->detach());

        $this->assert_stream_state_after_closed_or_detached($stream);

        $stream->close();
    }

    public function test_detach_non_seekable_stream_and_clear_properties(): void
    {
        $handle = popen('echo foo', 'r');
        $stream = new Stream($handle);
        self::assertFalse($stream->isSeekable());
        $detached = $stream->detach();
        self::assertIsResource($detached);
        self::assertNull($stream->detach());

        $this->assert_stream_state_after_closed_or_detached($stream);

        pclose($detached);
    }

    public function test_close_resource_and_clear_properties(): void
    {
        $handle = fopen('php://temp', 'r');
        $stream = new Stream($handle);
        $stream->close();

        self::assertFalse(is_resource($handle));

        $this->assert_stream_state_after_closed_or_detached($stream);
    }

    private function assert_stream_state_after_closed_or_detached(Stream $stream): void
    {
        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isWritable());
        self::assertFalse($stream->isSeekable());
        self::assertNull($stream->getSize());
        self::assertSame([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('foo'));

        $throws = function (callable $fn): void {
            try {
                $fn();
            } catch (\Exception $e) {
                $this->assertStringContainsString('Stream is detached', $e->getMessage());

                return;
            }

            $this->fail('Exception should be thrown after the stream is detached.');
        };

        $throws(function () use ($stream): void {
            $stream->read(10);
        });
        $throws(function () use ($stream): void {
            $stream->write('bar');
        });
        $throws(function () use ($stream): void {
            $stream->seek(10);
        });
        $throws(function () use ($stream): void {
            $stream->tell();
        });
        $throws(function () use ($stream): void {
            $stream->eof();
        });
        $throws(function () use ($stream): void {
            $stream->getContents();
        });

        if (\PHP_VERSION_ID >= 70400) {
            $throws(function () use ($stream): void {
                (string) $stream;
            });
        } else {
            $errors = [];
            set_error_handler(function (int $error_number, string $error_message) use (&$errors): void {
                $errors[] = ['message' => $error_message, 'number' => $error_number];
            });
            self::assertSame('', (string) $stream);
            restore_error_handler();

            self::assertCount(1, $errors);
            self::assertStringStartsWith('Guzzle_Http\Psr7\Stream::__toString exception', $errors[0]['message']);
            self::assertSame(E_USER_ERROR, $errors[0]['number']);
        }
    }

    public function test_stream_reading_with_zero_length(): void
    {
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);

        self::assertSame('', $stream->read(0));

        $stream->close();
    }

    public function test_stream_reading_with_negative_length(): void
    {
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Length parameter cannot be negative');

        try {
            $stream->read(-1);
        } catch (\Exception $e) {
            $stream->close();
            throw $e;
        }

        $stream->close();
    }

    public function test_stream_reading_fread_false(): void
    {
        self::$is_fread_error = true;
        $r = fopen('php://temp', 'r');
        $stream = new Stream($r);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read from stream');

        try {
            $stream->read(1);
        } catch (\Exception $e) {
            self::$is_fread_error = false;
            $stream->close();
            throw $e;
        }

        self::$is_fread_error = false;
        $stream->close();
    }

    public function test_stream_reading_fread_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read from stream');

        $r = Stream_Wrapper::get_resource(new Fn_Stream([
            'read' => function ($len): string {
                throw new \ErrorException('Some error');
            },
            'isReadable' => function (): bool {
                return true;
            },
            'isWritable' => function (): bool {
                return false;
            },
            'eof' => function (): bool {
                return false;
            },
        ]));

        $stream = new Stream($r);
        $stream->read(1);
    }

    /**
     * @requires extension zlib
     *
     * @dataProvider gzip_mode_provider
     */
    public function test_gzip_stream_modes(string $mode, bool $readable, bool $writable): void
    {
        $r = gzopen('php://temp', $mode);
        $stream = new Stream($r);

        self::assertSame($readable, $stream->isReadable());
        self::assertSame($writable, $stream->isWritable());

        $stream->close();
    }

    public static function gzip_mode_provider(): iterable
    {
        return [
            ['mode' => 'rb9', 'readable' => true, 'writable' => false],
            ['mode' => 'wb2', 'readable' => false, 'writable' => true],
        ];
    }

    /**
     * @dataProvider readable_mode_provider
     */
    public function test_readable_stream(string $mode): void
    {
        $r = fopen('php://temp', $mode);
        $stream = new Stream($r);

        self::assertTrue($stream->isReadable());

        $stream->close();
    }

    public static function readable_mode_provider(): iterable
    {
        return [
            ['r'],
            ['w+'],
            ['r+'],
            ['x+'],
            ['c+'],
            ['rb'],
            ['w+b'],
            ['r+b'],
            ['x+b'],
            ['c+b'],
            ['rt'],
            ['w+t'],
            ['r+t'],
            ['x+t'],
            ['c+t'],
            ['a+'],
            ['rb+'],
        ];
    }

    public function test_write_only_stream_is_not_readable(): void
    {
        $r = fopen('php://output', 'w');
        $stream = new Stream($r);

        self::assertFalse($stream->isReadable());

        $stream->close();
    }

    /**
     * @dataProvider writable_mode_provider
     */
    public function test_writable_stream(string $mode): void
    {
        $r = fopen('php://temp', $mode);
        $stream = new Stream($r);

        self::assertTrue($stream->isWritable());

        $stream->close();
    }

    public static function writable_mode_provider(): iterable
    {
        return [
            ['w'],
            ['w+'],
            ['rw'],
            ['r+'],
            ['x+'],
            ['c+'],
            ['wb'],
            ['w+b'],
            ['r+b'],
            ['rb+'],
            ['x+b'],
            ['c+b'],
            ['w+t'],
            ['r+t'],
            ['x+t'],
            ['c+t'],
            ['a'],
            ['a+'],
        ];
    }

    public function test_read_only_stream_is_not_writable(): void
    {
        $r = fopen('php://input', 'r');
        $stream = new Stream($r);

        self::assertFalse($stream->isWritable());

        $stream->close();
    }

    public function test_cannot_read_unreadable_stream(): void
    {
        $r = fopen(tempnam(sys_get_temp_dir(), 'guzzle-psr7-'), 'w');
        $stream = new Stream($r);

        $stream->write('Hello world!!');

        $stream->seek(0);

        $this->expectException(\RuntimeException::class);

        try {
            $stream->getContents();
        } finally {
            $stream->close();
        }
    }
}

namespace Guzzle_Http\Psr7;

use Guzzle_Http\Tests\Psr7\Stream_Test;

function fread($handle, $length)
{
    return Stream_Test::$is_fread_error ? false : \fread($handle, $length);
}
