<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use Guzzle_Http\Psr7\Inflate_Stream;
use Guzzle_Http\Psr7\No_Seek_Stream;
use PHPUnit\Framework\TestCase;

/**
 * @requires extension zlib
 */
class Inflate_Stream_Test extends TestCase
{
    public function test_inflates_rfc1952streams(): void
    {
        $content = gzencode('test');
        $a = Psr7\Utils::stream_for($content);
        $b = new Inflate_Stream($a);
        self::assertSame('test', (string) $b);
    }

    public function test_inflates_streams_rfc1952with_filename(): void
    {
        $content = $this->get_gzip_string_with_filename('test');
        $a = Psr7\Utils::stream_for($content);
        $b = new Inflate_Stream($a);
        self::assertSame('test', (string) $b);
    }

    public function test_inflates_rfc1950streams(): void
    {
        $content = gzcompress('test');
        $a = Psr7\Utils::stream_for($content);
        $b = new Inflate_Stream($a);
        self::assertSame('test', (string) $b);
    }

    public function test_inflates_rfc1952streams_with_extra_flags(): void
    {
        $content = gzdeflate('test'); // RFC 1951. Raw deflate. No header.

        //  +---+---+---+---+---+---+---+---+---+---+
        //  |ID1|ID2|CM |FLG|     MTIME     |XFL|OS | (more-->)
        //  +---+---+---+---+---+---+---+---+---+---+
        $header = "\x1f\x8B\x08";
        // set flags FHCRC, FEXTRA, FNAME and FCOMMENT
        $header .= chr(0b00011110);
        $header .= "\x00\x00\x00\x00"; // MTIME
        $header .= "\x02\x03"; // XFL, OS
        // 4 byte extra data
        $header .= "\x04\x00\x41\x70\x00\x00"; /* XLEN + EXTRA */
        // file name (2 bytes + terminator)
        $header .= "\x41\x70\x00";
        // file comment (3 bytes + terminator)
        $header .= "\x41\x42\x43\x00";

        // crc16
        $header .= pack('v', crc32($header));

        $a = Psr7\Utils::stream_for($header.$content);
        $b = new Inflate_Stream($a);
        self::assertSame('test', (string) $b);
    }

    public function test_inflates_streams_preserve_seekable(): void
    {
        $content = gzencode('test');
        $seekable = Psr7\Utils::stream_for($content);

        $seekable_inflate = new Inflate_Stream($seekable);
        self::assertTrue($seekable_inflate->isSeekable());
        self::assertSame('test', (string) $seekable_inflate);

        $non_seekable = new No_Seek_Stream(Psr7\Utils::stream_for($content));
        $non_seekable_inflate = new Inflate_Stream($non_seekable);
        self::assertFalse($non_seekable_inflate->isSeekable());
        self::assertSame('test', (string) $non_seekable_inflate);
    }

    private function get_gzip_string_with_filename($original_string)
    {
        $gzipped = bin2hex(gzencode($original_string));

        $header = substr($gzipped, 0, 20);
        // set FNAME flag
        $header[6] = 0;
        $header[7] = 8;
        // make a dummy filename
        $filename = '64756d6d7900';
        $rest = substr($gzipped, 20);

        return hex2bin($header.$filename.$rest);
    }
}
