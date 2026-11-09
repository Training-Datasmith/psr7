<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use Guzzle_Http\Psr7\Caching_Stream;
use Guzzle_Http\Psr7\Stream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Guzzle_Http\Psr7\Caching_Stream
 */
class Caching_Stream_Test extends TestCase
{
    /** @var Caching_Stream */
    private $body;
    /** @var Stream */
    private $decorated;

    protected function setUp(): void
    {
        $this->decorated = Psr7\Utils::stream_for('testing');
        $this->body = new Caching_Stream($this->decorated);
    }

    protected function tearDown(): void
    {
        $this->decorated->close();
        $this->body->close();
    }

    public function test_uses_remote_size_if_available(): void
    {
        $body = Psr7\Utils::stream_for('test');
        $caching = new Caching_Stream($body);
        self::assertSame(4, $caching->getSize());
    }

    public function test_uses_remote_size_if_not_available(): void
    {
        $body = new Psr7\Pump_Stream(function () {
            return 'a';
        });
        $caching = new Caching_Stream($body);
        self::assertNull($caching->getSize());
    }

    public function test_reads_until_cached_to_byte(): void
    {
        $this->body->seek(5);
        self::assertSame('n', $this->body->read(1));
        $this->body->seek(0);
        self::assertSame('t', $this->body->read(1));
    }

    public function test_can_seek_near_end_with_seek_end(): void
    {
        $base_stream = Psr7\Utils::stream_for(implode('', range('a', 'z')));
        $cached = new Caching_Stream($base_stream);
        $cached->seek(-1, SEEK_END);
        self::assertSame(25, $base_stream->tell());
        self::assertSame('z', $cached->read(1));
        self::assertSame(26, $cached->getSize());
    }

    public function test_can_seek_to_end_with_seek_end(): void
    {
        $base_stream = Psr7\Utils::stream_for(implode('', range('a', 'z')));
        $cached = new Caching_Stream($base_stream);
        $cached->seek(0, SEEK_END);
        self::assertSame(26, $base_stream->tell());
        self::assertSame('', $cached->read(1));
        self::assertSame(26, $cached->getSize());
    }

    public function test_can_use_seek_end_with_unknown_size(): void
    {
        $base_stream = Psr7\Utils::stream_for('testing');
        $decorated = Psr7\Fn_Stream::decorate($base_stream, [
            'getSize' => function () {
                return null;
            },
        ]);
        $cached = new Caching_Stream($decorated);
        $cached->seek(-1, SEEK_END);
        self::assertSame('g', $cached->read(1));
    }

    public function test_rewind(): void
    {
        $a = Psr7\Utils::stream_for('foo');
        $d = new Caching_Stream($a);
        self::assertSame('foo', $d->read(3));
        $d->rewind();
        self::assertSame('foo', $d->read(3));
    }

    public function test_can_seek_to_read_bytes(): void
    {
        self::assertSame('te', $this->body->read(2));
        $this->body->seek(0);
        self::assertSame('test', $this->body->read(4));
        self::assertSame(4, $this->body->tell());
        $this->body->seek(2);
        self::assertSame(2, $this->body->tell());
        $this->body->seek(2, SEEK_CUR);
        self::assertSame(4, $this->body->tell());
        self::assertSame('ing', $this->body->read(3));
    }

    public function test_can_seek_to_read_bytes_with_partial_body_returned(): void
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'testing');
        fseek($stream, 0);

        $this->decorated = $this->getMockBuilder(Stream::class)
            ->setConstructorArgs([$stream])
            ->onlyMethods(['read'])
            ->getMock();

        $this->decorated->expects(self::exactly(2))
            ->method('read')
            ->willReturnCallback(function (int $length) use ($stream) {
                return fread($stream, 2);
            });

        $this->body = new Caching_Stream($this->decorated);

        self::assertSame(0, $this->body->tell());
        $this->body->seek(4, SEEK_SET);
        self::assertSame(4, $this->body->tell());

        $this->body->seek(0);
        self::assertSame('test', $this->body->read(4));
    }

    public function test_writes_to_buffer_stream(): void
    {
        $this->body->read(2);
        $this->body->write('hi');
        $this->body->seek(0);
        self::assertSame('tehiing', (string) $this->body);
    }

    public function test_skips_overwritten_bytes(): void
    {
        $decorated = Psr7\Utils::stream_for(
            implode("\n", array_map(function ($n) {
                return str_pad((string) $n, 4, '0', STR_PAD_LEFT);
            }, range(0, 25)))
        );

        $body = new Caching_Stream($decorated);

        self::assertSame("0000\n", Psr7\Utils::read_line($body));
        self::assertSame("0001\n", Psr7\Utils::read_line($body));
        // Write over part of the body yet to be read, so skip some bytes
        self::assertSame(5, $body->write("TEST\n"));
        // Read, which skips bytes, then reads
        self::assertSame("0003\n", Psr7\Utils::read_line($body));
        self::assertSame("0004\n", Psr7\Utils::read_line($body));
        self::assertSame("0005\n", Psr7\Utils::read_line($body));

        // Overwrite part of the cached body (so don't skip any bytes)
        $body->seek(5);
        self::assertSame(5, $body->write("ABCD\n"));
        self::assertSame("TEST\n", Psr7\Utils::read_line($body));
        self::assertSame("0003\n", Psr7\Utils::read_line($body));
        self::assertSame("0004\n", Psr7\Utils::read_line($body));
        self::assertSame("0005\n", Psr7\Utils::read_line($body));
        self::assertSame("0006\n", Psr7\Utils::read_line($body));
        self::assertSame(5, $body->write("1234\n"));

        // Seek to 0 and ensure the overwritten bit is replaced
        $body->seek(0);
        self::assertSame("0000\nABCD\nTEST\n0003\n0004\n0005\n0006\n1234\n0008\n0009\n", $body->read(50));

        // Ensure that casting it to a string does not include the bit that was overwritten
        self::assertStringContainsString("0000\nABCD\nTEST\n0003\n0004\n0005\n0006\n1234\n0008\n0009\n", (string) $body);
    }

    public function test_closes_both_streams(): void
    {
        $s = fopen('php://temp', 'r');
        $a = Psr7\Utils::stream_for($s);
        $d = new Caching_Stream($a);
        $d->close();
        self::assertFalse(is_resource($s));
    }

    public function test_ensures_valid_whence(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid whence');
        $this->body->seek(10, -123456);
    }
}
