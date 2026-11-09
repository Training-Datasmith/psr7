<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use Guzzle_Http\Psr7\Append_Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class Append_Stream_Test extends TestCase
{
    public function test_validates_streams_are_readable(): void
    {
        $a = new Append_Stream();
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::once())
            ->method('isReadable')
            ->willReturn(false);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Each stream must be readable');
        $a->add_stream($s);
    }

    public function test_validates_seek_type(): void
    {
        $a = new Append_Stream();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The Append_Stream can only seek with SEEK_SET');
        $a->seek(100, SEEK_CUR);
    }

    public function test_tries_to_rewind_on_seek(): void
    {
        $a = new Append_Stream();
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::once())
            ->method('isReadable')
            ->willReturn(true);
        $s->expects(self::once())
            ->method('isSeekable')
            ->willReturn(true);
        $s->expects(self::once())
            ->method('rewind')
            ->willThrowException(new \RuntimeException());
        $a->add_stream($s);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to seek stream 0 of the Append_Stream');
        $a->seek(10);
    }

    public function test_seeks_to_position_by_reading(): void
    {
        $a = new Append_Stream([
            Psr7\Utils::stream_for('foo'),
            Psr7\Utils::stream_for('bar'),
            Psr7\Utils::stream_for('baz'),
        ]);

        $a->seek(3);
        self::assertSame(3, $a->tell());
        self::assertSame('bar', $a->read(3));

        $a->seek(6);
        self::assertSame(6, $a->tell());
        self::assertSame('baz', $a->read(3));
    }

    public function test_detach_without_streams(): void
    {
        $s = new Append_Stream();
        $s->detach();

        self::assertSame(0, $s->getSize());
        self::assertTrue($s->eof());
        self::assertTrue($s->isReadable());
        self::assertSame('', (string) $s);
        self::assertTrue($s->isSeekable());
        self::assertFalse($s->isWritable());
    }

    public function test_detaches_each_stream(): void
    {
        $handle = fopen('php://temp', 'r');

        $s1 = Psr7\Utils::stream_for($handle);
        $s2 = Psr7\Utils::stream_for('bar');
        $a = new Append_Stream([$s1, $s2]);

        $a->detach();

        self::assertSame(0, $a->getSize());
        self::assertTrue($a->eof());
        self::assertTrue($a->isReadable());
        self::assertSame('', (string) $a);
        self::assertTrue($a->isSeekable());
        self::assertFalse($a->isWritable());

        self::assertNull($s1->detach());
        self::assertIsResource($handle, 'resource is not closed when detaching');
        fclose($handle);
    }

    public function test_closes_each_stream(): void
    {
        $handle = fopen('php://temp', 'r');

        $s1 = Psr7\Utils::stream_for($handle);
        $s2 = Psr7\Utils::stream_for('bar');
        $a = new Append_Stream([$s1, $s2]);

        $a->close();

        self::assertSame(0, $a->getSize());
        self::assertTrue($a->eof());
        self::assertTrue($a->isReadable());
        self::assertSame('', (string) $a);
        self::assertTrue($a->isSeekable());
        self::assertFalse($a->isWritable());

        self::assertFalse(is_resource($handle));
    }

    public function test_is_not_writable(): void
    {
        $a = new Append_Stream([Psr7\Utils::stream_for('foo')]);
        self::assertFalse($a->isWritable());
        self::assertTrue($a->isSeekable());
        self::assertTrue($a->isReadable());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot write to an Append_Stream');
        $a->write('foo');
    }

    public function test_does_not_need_streams(): void
    {
        $a = new Append_Stream();
        self::assertSame('', (string) $a);
    }

    public function test_can_read_from_multiple_streams(): void
    {
        $a = new Append_Stream([
            Psr7\Utils::stream_for('foo'),
            Psr7\Utils::stream_for('bar'),
            Psr7\Utils::stream_for('baz'),
        ]);
        self::assertFalse($a->eof());
        self::assertSame(0, $a->tell());
        self::assertSame('foo', $a->read(3));
        self::assertSame('bar', $a->read(3));
        self::assertSame('baz', $a->read(3));
        self::assertSame('', $a->read(1));
        self::assertTrue($a->eof());
        self::assertSame(9, $a->tell());
        self::assertSame('foobarbaz', (string) $a);
    }

    public function test_can_determine_size_from_multiple_streams(): void
    {
        $a = new Append_Stream([
            Psr7\Utils::stream_for('foo'),
            Psr7\Utils::stream_for('bar'),
        ]);
        self::assertSame(6, $a->getSize());

        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::once())
            ->method('isSeekable')
            ->willReturn(false);
        $s->expects(self::once())
            ->method('isReadable')
            ->willReturn(true);
        $a->add_stream($s);
        self::assertNull($a->getSize());
    }

    /**
     * @requires PHP < 7.4
     */
    public function test_catches_exceptions_when_casting_to_string(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::once())
            ->method('isSeekable')
            ->willReturn(true);
        $s->expects(self::once())
            ->method('read')
            ->willThrowException(new \RuntimeException('foo'));
        $s->expects(self::once())
            ->method('isReadable')
            ->willReturn(true);
        $s->expects(self::any())
            ->method('eof')
            ->willReturn(false);
        $a = new Append_Stream([$s]);
        self::assertFalse($a->eof());

        $errors = [];
        set_error_handler(static function (int $error_number, string $error_message) use (&$errors): bool {
            $errors[] = ['number' => $error_number, 'message' => $error_message];

            return true;
        });
        (string) $a;

        restore_error_handler();

        self::assertCount(1, $errors);
        self::assertSame(E_USER_ERROR, $errors[0]['number']);
        self::assertStringStartsWith('Guzzle_Http\Psr7\Append_Stream::__toString exception:', $errors[0]['message']);
    }

    public function test_returns_empty_metadata(): void
    {
        $s = new Append_Stream();
        self::assertSame([], $s->getMetadata());
        self::assertNull($s->getMetadata('foo'));
    }
}
