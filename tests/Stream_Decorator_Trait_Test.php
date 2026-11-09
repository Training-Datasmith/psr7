<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use Guzzle_Http\Psr7\Stream_Decorator_Trait;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class Str implements StreamInterface
{
    use Stream_Decorator_Trait;

    /** @var StreamInterface */
    private $stream;
}

/**
 * @covers \Guzzle_Http\Psr7\Stream_Decorator_Trait
 */
class Stream_Decorator_Trait_Test extends TestCase
{
    /** @var StreamInterface */
    private $a;
    /** @var StreamInterface */
    private $b;
    /** @var resource */
    private $c;

    protected function setUp(): void
    {
        $this->c = fopen('php://temp', 'r+');
        fwrite($this->c, 'foo');
        fseek($this->c, 0);
        $this->a = Psr7\Utils::stream_for($this->c);
        $this->b = new Str($this->a);
    }

    /**
     * @requires PHP < 7.4
     */
    public function test_catches_exceptions_when_casting_to_string(): void
    {
        $s = $this->createMock(Str::class);
        $s->expects(self::once())
            ->method('read')
            ->willThrowException(new \RuntimeException('foo'));
        $msg = '';
        set_error_handler(function (int $err_no, string $str) use (&$msg): void {
            $msg = $str;
        });
        echo new Str($s);
        restore_error_handler();
        self::assertStringContainsString('foo', $msg);
    }

    public function test_to_string(): void
    {
        self::assertSame('foo', (string) $this->b);
    }

    public function test_has_size(): void
    {
        self::assertSame(3, $this->b->getSize());
    }

    public function test_reads(): void
    {
        self::assertSame('foo', $this->b->read(10));
    }

    public function test_check_methods(): void
    {
        self::assertSame($this->a->isReadable(), $this->b->isReadable());
        self::assertSame($this->a->isWritable(), $this->b->isWritable());
        self::assertSame($this->a->isSeekable(), $this->b->isSeekable());
    }

    public function test_seeks_and_tells(): void
    {
        $this->b->seek(1);
        self::assertSame(1, $this->a->tell());
        self::assertSame(1, $this->b->tell());
        $this->b->seek(0);
        self::assertSame(0, $this->a->tell());
        self::assertSame(0, $this->b->tell());
        $this->b->seek(0, SEEK_END);
        self::assertSame(3, $this->a->tell());
        self::assertSame(3, $this->b->tell());
    }

    public function test_gets_contents(): void
    {
        self::assertSame('foo', $this->b->getContents());
        self::assertSame('', $this->b->getContents());
        $this->b->seek(1);
        self::assertSame('oo', $this->b->getContents());
    }

    public function test_closes(): void
    {
        $this->b->close();
        self::assertFalse(is_resource($this->c));
    }

    public function test_detaches(): void
    {
        $this->b->detach();
        self::assertFalse($this->b->isReadable());
    }

    public function test_wraps_metadata(): void
    {
        self::assertSame($this->b->getMetadata(), $this->a->getMetadata());
        self::assertSame($this->b->getMetadata('uri'), $this->a->getMetadata('uri'));
    }

    public function test_wraps_writes(): void
    {
        $this->b->seek(0, SEEK_END);
        $this->b->write('foo');
        self::assertSame('foofoo', (string) $this->a);
    }

    public function test_throws_with_invalid_getter(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->b->foo;
    }

    public function test_throws_when_getter_not_implemented(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $s = new Bad_Stream();
        $s->stream;
    }
}

class Bad_Stream
{
    use Stream_Decorator_Trait;

    public function __construct()
    {
    }
}
