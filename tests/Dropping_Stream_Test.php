<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7\Buffer_Stream;
use Guzzle_Http\Psr7\Dropping_Stream;
use PHPUnit\Framework\TestCase;

class Dropping_Stream_Test extends TestCase
{
    public function test_begins_dropping_when_size_exceeded(): void
    {
        $stream = new Buffer_Stream();
        $drop = new Dropping_Stream($stream, 5);
        self::assertSame(3, $drop->write('hel'));
        self::assertSame(2, $drop->write('lo'));
        self::assertSame(5, $drop->getSize());
        self::assertSame('hello', $drop->read(5));
        self::assertSame(0, $drop->getSize());
        $drop->write('12345678910');
        self::assertSame(5, $stream->getSize());
        self::assertSame(5, $drop->getSize());
        self::assertSame('12345', (string) $drop);
        self::assertSame(0, $drop->getSize());
        $drop->write('hello');
        self::assertSame(0, $drop->write('test'));
    }
}
