<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7\Stream;
use Guzzle_Http\Psr7\Uploaded_File;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @covers \Guzzle_Http\Psr7\Uploaded_File
 */
class Uploaded_File_Test extends TestCase
{
    private $cleanup;

    protected function setUp(): void
    {
        $this->cleanup = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanup as $file) {
            if (is_scalar($file) && file_exists($file)) {
                unlink($file);
            }
        }
    }

    public static function invalid_streams()
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'int' => [1],
            'float' => [1.1],
            'array' => [['filename']],
            'object' => [(object) ['filename']],
        ];
    }

    /**
     * @dataProvider invalid_streams
     */
    public function test_raises_exception_on_invalid_stream_or_file($stream_or_file): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Uploaded_File($stream_or_file, 0, UPLOAD_ERR_OK);
    }

    public function test_get_stream_returns_original_stream_object(): void
    {
        $stream = new Stream(fopen('php://temp', 'r'));
        $upload = new Uploaded_File($stream, 0, UPLOAD_ERR_OK);

        self::assertSame($stream, $upload->getStream());
    }

    public function test_get_stream_returns_wrapped_php_stream(): void
    {
        $stream = fopen('php://temp', 'wb+');
        $upload = new Uploaded_File($stream, 0, UPLOAD_ERR_OK);
        $upload_stream = $upload->getStream()->detach();

        self::assertSame($stream, $upload_stream);
    }

    public function test_get_stream_returns_stream_for_file(): void
    {
        $this->cleanup[] = $stream = tempnam(sys_get_temp_dir(), 'stream_file');
        $upload = new Uploaded_File($stream, 0, UPLOAD_ERR_OK);
        $upload_stream = $upload->getStream();
        $r = new ReflectionProperty($upload_stream, 'filename');

        if (PHP_VERSION_ID < 80100) {
            $r->setAccessible(true);
        }

        self::assertSame($stream, $r->getValue($upload_stream));
    }

    public function test_successful(): void
    {
        $stream = \Guzzle_Http\Psr7\Utils::stream_for('Foo bar!');
        $upload = new Uploaded_File($stream, $stream->getSize(), UPLOAD_ERR_OK, 'filename.txt', 'text/plain');

        self::assertSame($stream->getSize(), $upload->getSize());
        self::assertSame('filename.txt', $upload->getClientFilename());
        self::assertSame('text/plain', $upload->getClientMediaType());

        $this->cleanup[] = $to = tempnam(sys_get_temp_dir(), 'successful');
        $upload->moveTo($to);
        self::assertFileExists($to);
        self::assertSame($stream->__toString(), file_get_contents($to));
    }

    public static function invalid_move_paths(): iterable
    {
        return [
            'null' => [null],
            'true' => [true],
            'false' => [false],
            'int' => [1],
            'float' => [1.1],
            'empty' => [''],
            'array' => [['filename']],
            'object' => [(object) ['filename']],
        ];
    }

    /**
     * @dataProvider invalid_move_paths
     */
    public function test_move_raises_exception_for_invalid_path($path): void
    {
        $stream = \Guzzle_Http\Psr7\Utils::stream_for('Foo bar!');
        $upload = new Uploaded_File($stream, 0, UPLOAD_ERR_OK);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('path');
        $upload->moveTo($path);
    }

    public function test_move_cannot_be_called_more_than_once(): void
    {
        $stream = \Guzzle_Http\Psr7\Utils::stream_for('Foo bar!');
        $upload = new Uploaded_File($stream, 0, UPLOAD_ERR_OK);

        $this->cleanup[] = $to = tempnam(sys_get_temp_dir(), 'diac');
        $upload->moveTo($to);
        self::assertFileExists($to);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('moved');
        $upload->moveTo($to);
    }

    public function test_cannot_retrieve_stream_after_move(): void
    {
        $stream = \Guzzle_Http\Psr7\Utils::stream_for('Foo bar!');
        $upload = new Uploaded_File($stream, 0, UPLOAD_ERR_OK);

        $this->cleanup[] = $to = tempnam(sys_get_temp_dir(), 'diac');
        $upload->moveTo($to);
        self::assertFileExists($to);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('moved');
        $upload->getStream();
    }

    public static function non_ok_error_status(): iterable
    {
        return [
            'UPLOAD_ERR_INI_SIZE' => [UPLOAD_ERR_INI_SIZE],
            'UPLOAD_ERR_FORM_SIZE' => [UPLOAD_ERR_FORM_SIZE],
            'UPLOAD_ERR_PARTIAL' => [UPLOAD_ERR_PARTIAL],
            'UPLOAD_ERR_NO_FILE' => [UPLOAD_ERR_NO_FILE],
            'UPLOAD_ERR_NO_TMP_DIR' => [UPLOAD_ERR_NO_TMP_DIR],
            'UPLOAD_ERR_CANT_WRITE' => [UPLOAD_ERR_CANT_WRITE],
            'UPLOAD_ERR_EXTENSION' => [UPLOAD_ERR_EXTENSION],
        ];
    }

    /**
     * @dataProvider non_ok_error_status
     */
    public function test_constructor_does_not_raise_exception_for_invalid_stream_when_error_status_present($status): void
    {
        $uploaded_file = new Uploaded_File('not ok', 0, $status);
        self::assertSame($status, $uploaded_file->getError());
    }

    /**
     * @dataProvider non_ok_error_status
     */
    public function test_move_to_raises_exception_when_error_status_present($status): void
    {
        $uploaded_file = new Uploaded_File('not ok', 0, $status);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('upload error');
        $uploaded_file->moveTo(__DIR__.'/'.bin2hex(random_bytes(20)));
    }

    /**
     * @dataProvider non_ok_error_status
     */
    public function test_get_stream_raises_exception_when_error_status_present($status): void
    {
        $uploaded_file = new Uploaded_File('not ok', 0, $status);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('upload error');
        $uploaded_file->getStream();
    }

    public function test_move_to_creates_stream_if_only_a_filename_was_provided(): void
    {
        $this->cleanup[] = $from = tempnam(sys_get_temp_dir(), 'copy_from');
        $this->cleanup[] = $to = tempnam(sys_get_temp_dir(), 'copy_to');

        copy(__FILE__, $from);

        $uploaded_file = new Uploaded_File($from, 100, UPLOAD_ERR_OK, basename($from), 'text/plain');
        $uploaded_file->moveTo($to);

        self::assertFileEquals(__FILE__, $to);
    }
}
