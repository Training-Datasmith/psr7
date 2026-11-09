<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use Guzzle_Http\Psr7\Fn_Stream;
use Guzzle_Http\Psr7\Limit_Stream;
use Guzzle_Http\Psr7\No_Seek_Stream;
use Guzzle_Http\Psr7\Stream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Guzzle_Http\Psr7\Limit_Stream
 */
class Limit_Stream_Test extends TestCase
{
    /** @var Limit_Stream */
    private $body;

    /** @var Stream */
    private $decorated;

    protected function setUp(): void
    {
        $this->decorated = Psr7\Utils::stream_for(fopen(__FILE__, 'r'));
        $this->body = new Limit_Stream($this->decorated, 10, 3);
    }

    public function testReturnsSubset(): void
    {
        $body = new Limit_Stream(Psr7\Utils::stream_for('foo'), -1, 1);
        self::assertSame('oo', (string) $body);
        self::assertTrue($body->eof());
        $body->seek(0);
        self::assertFalse($body->eof());
        self::assertSame('oo', $body->read(100));
        self::assertSame('', $body->read(1));
        self::assertTrue($body->eof());
    }

    public function testReturnsSubsetWhenCastToString(): void
    {
        $body = Psr7\Utils::stream_for('foo_baz_bar');
        $limited = new Limit_Stream($body, 3, 4);
        self::assertSame('baz', (string) $limited);
    }

    public function testReturnsSubsetOfEmptyBodyWhenCastToString(): void
    {
        $body = Psr7\Utils::stream_for('01234567891234');
        $limited = new Limit_Stream($body, 0, 10);
        self::assertSame('', (string) $limited);
    }

    public function testReturnsSpecificSubsetOBodyWhenCastToString(): void
    {
        $body = Psr7\Utils::stream_for('0123456789abcdef');
        $limited = new Limit_Stream($body, 3, 10);
        self::assertSame('abc', (string) $limited);
    }

    public function testSeeksWhenConstructed(): void
    {
        self::assertSame(0, $this->body->tell());
        self::assertSame(3, $this->decorated->tell());
    }

    public function testAllowsBoundedSeek(): void
    {
        $this->body->seek(100);
        self::assertSame(10, $this->body->tell());
        self::assertSame(13, $this->decorated->tell());
        $this->body->seek(0);
        self::assertSame(0, $this->body->tell());
        self::assertSame(3, $this->decorated->tell());
        try {
            $this->body->seek(-10);
            self::fail();
        } catch (\RuntimeException $e) {
        }
        self::assertSame(0, $this->body->tell());
        self::assertSame(3, $this->decorated->tell());
        $this->body->seek(5);
        self::assertSame(5, $this->body->tell());
        self::assertSame(8, $this->decorated->tell());
        // Fail
        try {
            $this->body->seek(1000, SEEK_END);
            self::fail();
        } catch (\RuntimeException $e) {
        }
    }

    public function testReadsOnlySubsetOfData(): void
    {
        $data = $this->body->read(100);
        self::assertSame(10, strlen($data));
        self::assertSame('', $this->body->read(1000));

        $this->body->set_offset(10);
        $newData = $this->body->read(100);
        self::assertSame(10, strlen($newData));
        self::assertNotSame($data, $newData);
    }

    public function testThrowsWhenCurrentGreaterThanOffsetSeek(): void
    {
        $a = Psr7\Utils::stream_for('foo_bar');
        $b = new No_Seek_Stream($a);
        $c = new Limit_Stream($b);
        $a->getContents();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not seek to stream offset 2');
        $c->set_offset(2);
    }

    public function testCanGetContentsWithoutSeeking(): void
    {
        $a = Psr7\Utils::stream_for('foo_bar');
        $b = new No_Seek_Stream($a);
        $c = new Limit_Stream($b);
        self::assertSame('foo_bar', $c->getContents());
    }

    public function testClaimsConsumedWhenReadLimitIsReached(): void
    {
        self::assertFalse($this->body->eof());
        $this->body->read(1000);
        self::assertTrue($this->body->eof());
    }

    public function testContentLengthIsBounded(): void
    {
        self::assertSame(10, $this->body->getSize());
    }

    public function testGetContentsIsBasedOnSubset(): void
    {
        $body = new Limit_Stream(Psr7\Utils::stream_for('foobazbar'), 3, 3);
        self::assertSame('baz', $body->getContents());
    }

    public function testReturnsNullIfSizeCannotBeDetermined(): void
    {
        $a = new Fn_Stream([
            'getSize' => function () {
                return null;
            },
            'tell' => function () {
                return 0;
            },
        ]);
        $b = new Limit_Stream($a);
        self::assertNull($b->getSize());
    }

    public function testLengthLessOffsetWhenNoLimitSize(): void
    {
        $a = Psr7\Utils::stream_for('foo_bar');
        $b = new Limit_Stream($a, -1, 4);
        self::assertSame(3, $b->getSize());
    }
}
