<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7\Buffer_Stream;
use PHPUnit\Framework\TestCase;

class Buffer_Stream_Test extends TestCase
{
    public function test_has_metadata(): void
    {
        $b = new Buffer_Stream(10);
        self::assertTrue($b->isReadable());
        self::assertTrue($b->isWritable());
        self::assertFalse($b->isSeekable());
        self::assertNull($b->getMetadata('foo'));
        self::assertSame(10, $b->getMetadata('hwm'));
        self::assertSame([], $b->getMetadata());
    }

    public function test_removes_read_data_from_buffer(): void
    {
        $b = new Buffer_Stream();
        self::assertSame(3, $b->write('foo'));
        self::assertSame(3, $b->getSize());
        self::assertFalse($b->eof());
        self::assertSame('foo', $b->read(10));
        self::assertTrue($b->eof());
        self::assertSame('', $b->read(10));
    }

    public function test_can_cast_to_string_or_get_contents(): void
    {
        $b = new Buffer_Stream();
        $b->write('foo');
        $b->write('baz');
        self::assertSame('foo', $b->read(3));
        $b->write('bar');
        self::assertSame('bazbar', (string) $b);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot determine the position of a Buffer_Stream');
        $b->tell();
    }

    public function test_detach_clears_buffer(): void
    {
        $b = new Buffer_Stream();
        $b->write('foo');
        $b->detach();
        self::assertTrue($b->eof());
        self::assertSame(3, $b->write('abc'));
        self::assertSame('abc', $b->read(10));
    }

    public function test_exceeding_highwater_mark_returns_false_but_still_buffers(): void
    {
        $b = new Buffer_Stream(5);
        self::assertSame(3, $b->write('hi '));
        self::assertSame(0, $b->write('hello'));
        self::assertSame('hi hello', (string) $b);
        self::assertSame(4, $b->write('test'));
    }
}
