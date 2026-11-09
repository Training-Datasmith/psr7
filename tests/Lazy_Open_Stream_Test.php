<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7\Lazy_Open_Stream;
use PHPUnit\Framework\TestCase;

class Lazy_Open_Stream_Test extends TestCase
{
    private $fname;

    protected function setUp(): void
    {
        $this->fname = tempnam(sys_get_temp_dir(), 'tfile');

        if (file_exists($this->fname)) {
            unlink($this->fname);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->fname)) {
            unlink($this->fname);
        }
    }

    public function testOpensLazily(): void
    {
        $l = new Lazy_Open_Stream($this->fname, 'w+');
        $l->write('foo');
        self::assertIsArray($l->getMetadata());
        self::assertFileExists($this->fname);
        self::assertSame('foo', file_get_contents($this->fname));
        self::assertSame('foo', (string) $l);
    }

    public function testProxiesToFile(): void
    {
        file_put_contents($this->fname, 'foo');
        $l = new Lazy_Open_Stream($this->fname, 'r');
        self::assertSame('foo', $l->read(4));
        self::assertTrue($l->eof());
        self::assertSame(3, $l->tell());
        self::assertTrue($l->isReadable());
        self::assertTrue($l->isSeekable());
        self::assertFalse($l->isWritable());
        $l->seek(1);
        self::assertSame('oo', $l->getContents());
        self::assertSame('foo', (string) $l);
        self::assertSame(3, $l->getSize());
        self::assertIsArray($l->getMetadata());
        $l->close();
    }

    public function testDetachesUnderlyingStream(): void
    {
        file_put_contents($this->fname, 'foo');
        $l = new Lazy_Open_Stream($this->fname, 'r');
        $r = $l->detach();
        self::assertIsResource($r);
        fseek($r, 0);
        self::assertSame('foo', stream_get_contents($r));
        fclose($r);
    }
}
