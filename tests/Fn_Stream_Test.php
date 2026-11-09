<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use Guzzle_Http\Psr7\Fn_Stream;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Guzzle_Http\Psr7\Fn_Stream
 */
class Fn_Stream_Test extends TestCase
{
    public function testThrowsWhenNotImplemented(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('seek() is not implemented in the Fn_Stream');
        (new Fn_Stream([]))->seek(1);
    }

    public function testProxiesToFunction(): void
    {
        $s = new Fn_Stream([
            'read' => function ($len) {
                $this->assertSame(3, $len);

                return 'foo';
            },
        ]);

        self::assertSame('foo', $s->read(3));
    }

    public function testCanCloseOnDestruct(): void
    {
        $called = false;
        $s = new Fn_Stream([
            'close' => function () use (&$called): void {
                $called = true;
            },
        ]);
        unset($s);
        self::assertTrue($called);
    }

    public function testDoesNotRequireClose(): void
    {
        $s = new Fn_Stream([]);
        unset($s);
        self::assertTrue(true); // strict mode requires an assertion
    }

    public function testDecoratesStream(): void
    {
        $a = Psr7\Utils::stream_for('foo');
        $b = Fn_Stream::decorate($a, []);
        self::assertSame(3, $b->getSize());
        self::assertSame($b->isWritable(), true);
        self::assertSame($b->isReadable(), true);
        self::assertSame($b->isSeekable(), true);
        self::assertSame($b->read(3), 'foo');
        self::assertSame($b->tell(), 3);
        self::assertSame($a->tell(), 3);
        self::assertSame('', $a->read(1));
        self::assertSame($b->eof(), true);
        self::assertSame($a->eof(), true);
        $b->seek(0);
        self::assertSame('foo', (string) $b);
        $b->seek(0);
        self::assertSame('foo', $b->getContents());
        self::assertSame($a->getMetadata(), $b->getMetadata());
        $b->seek(0, SEEK_END);
        $b->write('bar');
        self::assertSame('foobar', (string) $b);
        self::assertIsResource($b->detach());
        $b->close();
    }

    public function testDecoratesWithCustomizations(): void
    {
        $called = false;
        $a = Psr7\Utils::stream_for('foo');
        $b = Fn_Stream::decorate($a, [
            'read' => function ($len) use (&$called, $a) {
                $called = true;

                return $a->read($len);
            },
        ]);
        self::assertSame('foo', $b->read(3));
        self::assertTrue($called);
    }

    public function testDoNotAllowUnserialization(): void
    {
        $a = new Fn_Stream([]);
        $b = serialize($a);
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Fn_Stream should never be unserialized');
        unserialize($b);
    }

    /**
     * @requires PHP < 7.4
     */
    public function testThatConvertingStreamToStringWillTriggerErrorAndWillReturnEmptyString(): void
    {
        $a = new Fn_Stream([
            '__toString' => function (): void {
                throw new \Exception();
            },
        ]);

        $errors = [];
        set_error_handler(function (int $errorNumber, string $errorMessage) use (&$errors): void {
            $errors[] = ['number' => $errorNumber, 'message' => $errorMessage];
        });
        (string) $a;

        restore_error_handler();

        self::assertCount(1, $errors);
        self::assertSame(E_USER_ERROR, $errors[0]['number']);
        self::assertStringStartsWith('Guzzle_Http\Psr7\Fn_Stream::__toString exception:', $errors[0]['message']);
    }
}
