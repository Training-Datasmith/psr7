<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7\No_Seek_Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \Guzzle_Http\Psr7\No_Seek_Stream
 * @covers \Guzzle_Http\Psr7\Stream_Decorator_Trait
 */
class No_Seek_Stream_Test extends TestCase
{
    public function testCannotSeek(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::never())->method('seek');
        $s->expects(self::never())->method('isSeekable');
        $wrapped = new No_Seek_Stream($s);
        self::assertFalse($wrapped->isSeekable());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot seek a No_Seek_Stream');
        $wrapped->seek(2);
    }

    public function testToStringDoesNotSeek(): void
    {
        $s = \Guzzle_Http\Psr7\Utils::stream_for('foo');
        $s->seek(1);
        $wrapped = new No_Seek_Stream($s);
        self::assertSame('oo', (string) $wrapped);

        $wrapped->close();
    }
}
