<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use Guzzle_Http\Psr7\Fn_Stream;
use Guzzle_Http\Psr7\No_Seek_Stream;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

class Utils_Test extends TestCase
{
    public function test_copies_to_string(): void
    {
        $s = Psr7\Utils::stream_for('foobaz');
        self::assertSame('foobaz', Psr7\Utils::copy_to_string($s));
        $s->seek(0);
        self::assertSame('foo', Psr7\Utils::copy_to_string($s, 3));
        self::assertSame('baz', Psr7\Utils::copy_to_string($s, 3));
        self::assertSame('', Psr7\Utils::copy_to_string($s));
    }

    public function test_copies_to_string_stops_when_read_fails(): void
    {
        $s1 = Psr7\Utils::stream_for('foobaz');
        $s1 = Fn_Stream::decorate($s1, [
            'read' => function () {
                return '';
            },
        ]);
        $result = Psr7\Utils::copy_to_string($s1);
        self::assertSame('', $result);
    }

    public function test_copies_to_stream(): void
    {
        $s1 = Psr7\Utils::stream_for('foobaz');
        $s2 = Psr7\Utils::stream_for('');
        Psr7\Utils::copy_to_stream($s1, $s2);
        self::assertSame('foobaz', (string) $s2);
        $s2 = Psr7\Utils::stream_for('');
        $s1->seek(0);
        Psr7\Utils::copy_to_stream($s1, $s2, 3);
        self::assertSame('foo', (string) $s2);
        Psr7\Utils::copy_to_stream($s1, $s2, 3);
        self::assertSame('foobaz', (string) $s2);
    }

    public function test_stops_copy_to_stream_when_write_fails(): void
    {
        $s1 = Psr7\Utils::stream_for('foobaz');
        $s2 = Psr7\Utils::stream_for('');
        $s2 = Fn_Stream::decorate($s2, [
            'write' => function () {
                return 0;
            },
        ]);
        Psr7\Utils::copy_to_stream($s1, $s2);
        self::assertSame('', (string) $s2);
    }

    public function test_stops_copy_to_steam_when_write_fails_with_max_len(): void
    {
        $s1 = Psr7\Utils::stream_for('foobaz');
        $s2 = Psr7\Utils::stream_for('');
        $s2 = Fn_Stream::decorate($s2, [
            'write' => function () {
                return 0;
            },
        ]);
        Psr7\Utils::copy_to_stream($s1, $s2, 10);
        self::assertSame('', (string) $s2);
    }

    public function test_copy_to_stream_reads_in_chunks_instead_of_all_in_memory(): void
    {
        $sizes = [];
        $s1 = new Fn_Stream([
            'eof' => function () {
                return false;
            },
            'read' => function ($size) use (&$sizes) {
                $sizes[] = $size;

                return str_repeat('.', $size);
            },
        ]);
        $s2 = Psr7\Utils::stream_for('');
        Psr7\Utils::copy_to_stream($s1, $s2, 16394);
        $s2->seek(0);
        self::assertSame(16394, strlen($s2->getContents()));
        self::assertSame(8192, $sizes[0]);
        self::assertSame(8192, $sizes[1]);
        self::assertSame(10, $sizes[2]);
    }

    public function test_stops_copy_to_steam_when_read_fails_with_max_len(): void
    {
        $s1 = Psr7\Utils::stream_for('foobaz');
        $s1 = Fn_Stream::decorate($s1, [
            'read' => function () {
                return '';
            },
        ]);
        $s2 = Psr7\Utils::stream_for('');
        Psr7\Utils::copy_to_stream($s1, $s2, 10);
        self::assertSame('', (string) $s2);
    }

    public function test_reads_lines(): void
    {
        $s = Psr7\Utils::stream_for("foo\nbaz\nbar");
        self::assertSame("foo\n", Psr7\Utils::read_line($s));
        self::assertSame("baz\n", Psr7\Utils::read_line($s));
        self::assertSame('bar', Psr7\Utils::read_line($s));
    }

    public function test_reads_lines_up_to_max_length(): void
    {
        $s = Psr7\Utils::stream_for("12345\n");
        self::assertSame('123', Psr7\Utils::read_line($s, 4));
        self::assertSame("45\n", Psr7\Utils::read_line($s));
    }

    public function test_read_lines_eof(): void
    {
        // Should return empty string on EOF
        $s = Psr7\Utils::stream_for("foo\nbar");
        while (!$s->eof()) {
            Psr7\Utils::read_line($s);
        }
        self::assertSame('', Psr7\Utils::read_line($s));
    }

    public function test_reads_line_until_empty_string_returned_from_read(): void
    {
        $s = $this->createMock(StreamInterface::class);
        $s->expects(self::exactly(2))
            ->method('read')
            ->willReturnCallback(function () {
                static $called = false;
                if ($called) {
                    return '';
                }
                $called = true;

                return 'h';
            });
        $s->expects(self::exactly(2))
            ->method('eof')
            ->willReturn(false);
        self::assertSame('h', Psr7\Utils::read_line($s));
    }

    public function test_redact_user_info(): void
    {
        $uri = new Psr7\Uri('http://my_user:secretPass@localhost/');

        $redacted_uri = Psr7\Utils::redact_user_info($uri);

        self::assertSame('http://my_user:***@localhost/', (string) $redacted_uri);
    }

    public function test_calculates_hash(): void
    {
        $s = Psr7\Utils::stream_for('foobazbar');
        self::assertSame(md5('foobazbar'), Psr7\Utils::hash($s, 'md5'));
    }

    public function test_calculates_hash_throws_when_seek_fails(): void
    {
        $s = new No_Seek_Stream(Psr7\Utils::stream_for('foobazbar'));
        $s->read(2);

        $this->expectException(\RuntimeException::class);

        Psr7\Utils::hash($s, 'md5');
    }

    public function test_calculates_hash_seeks_to_original_position(): void
    {
        $s = Psr7\Utils::stream_for('foobazbar');
        $s->seek(4);
        self::assertSame(md5('foobazbar'), Psr7\Utils::hash($s, 'md5'));
        self::assertSame(4, $s->tell());
    }

    public function test_opens_files_successfully(): void
    {
        $r = Psr7\Utils::try_fopen(__FILE__, 'r');
        self::assertIsResource($r);
        fclose($r);
    }

    public function test_throws_exception_not_warning(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open "/path/to/does/not/exist" using mode "r"');

        Psr7\Utils::try_fopen('/path/to/does/not/exist', 'r');
    }

    public function test_throws_exception_not_value_error(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to open "" using mode "r"');

        Psr7\Utils::try_fopen('', 'r');
    }

    /**
     * @requires PHP 7.4
     */
    public function test_gets_contents_throw_exception_when_not_readable(): void
    {
        $r = fopen(tempnam(sys_get_temp_dir(), 'guzzle-psr7-'), 'w');
        fwrite($r, 'hello world!');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read stream contents');

        try {
            Psr7\Utils::try_get_contents($r);
        } finally {
            fclose($r);
        }
    }

    public function test_gets_contents_throw_exception_when_closed(): void
    {
        $r = fopen(tempnam(sys_get_temp_dir(), 'guzzle-psr7-'), 'r+');
        fclose($r);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to read stream contents');

        Psr7\Utils::try_get_contents($r);
    }

    public function test_creates_uri_for_value(): void
    {
        self::assertInstanceOf('Guzzle_Http\Psr7\Uri', Psr7\Utils::uri_for('/foo'));
        self::assertInstanceOf(
            'Guzzle_Http\Psr7\Uri',
            Psr7\Utils::uri_for(new Psr7\Uri('/foo'))
        );
    }

    public function test_validates_uri(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Utils::uri_for([]);
    }

    public function test_keeps_position_of_resource(): void
    {
        $h = fopen(__FILE__, 'r');
        fseek($h, 10);
        $stream = Psr7\Utils::stream_for($h);
        self::assertSame(10, $stream->tell());
        $stream->close();
    }

    public function test_creates_with_factory(): void
    {
        $stream = Psr7\Utils::stream_for('foo');
        self::assertInstanceOf('Guzzle_Http\Psr7\Stream', $stream);
        self::assertSame('foo', $stream->getContents());
        $stream->close();
    }

    public function test_factory_creates_from_empty_string(): void
    {
        $s = Psr7\Utils::stream_for();
        self::assertInstanceOf('Guzzle_Http\Psr7\Stream', $s);
    }

    public function test_factory_creates_from_null(): void
    {
        $s = Psr7\Utils::stream_for(null);
        self::assertInstanceOf('Guzzle_Http\Psr7\Stream', $s);
    }

    public function test_factory_creates_from_resource(): void
    {
        $r = fopen(__FILE__, 'r');
        $s = Psr7\Utils::stream_for($r);
        self::assertInstanceOf('Guzzle_Http\Psr7\Stream', $s);
        self::assertSame(file_get_contents(__FILE__), (string) $s);
    }

    public function test_factory_creates_from_object_with_to_string(): void
    {
        $r = new Has_To_String();
        $s = Psr7\Utils::stream_for($r);
        self::assertInstanceOf('Guzzle_Http\Psr7\Stream', $s);
        self::assertSame('foo', (string) $s);
    }

    public function test_create_passes_through(): void
    {
        $s = Psr7\Utils::stream_for('foo');
        self::assertSame($s, Psr7\Utils::stream_for($s));
    }

    public function test_throws_exception_for_unknown(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Psr7\Utils::stream_for(new \stdClass());
    }

    public function test_returns_custom_metadata(): void
    {
        $s = Psr7\Utils::stream_for('foo', ['metadata' => ['hwm' => 3]]);
        self::assertSame(3, $s->getMetadata('hwm'));
        self::assertArrayHasKey('hwm', $s->getMetadata());
    }

    public function test_can_set_size(): void
    {
        $s = Psr7\Utils::stream_for('', ['size' => 10]);
        self::assertSame(10, $s->getSize());
    }

    public function test_can_create_iterator_based_stream(): void
    {
        $a = new \ArrayIterator(['foo', 'bar', '123']);
        $p = Psr7\Utils::stream_for($a);
        self::assertInstanceOf('Guzzle_Http\Psr7\Pump_Stream', $p);
        self::assertSame('foo', $p->read(3));
        self::assertFalse($p->eof());
        self::assertSame('b', $p->read(1));
        self::assertSame('a', $p->read(1));
        self::assertSame('r12', $p->read(3));
        self::assertFalse($p->eof());
        self::assertSame('3', $p->getContents());
        self::assertTrue($p->eof());
        self::assertSame(9, $p->tell());
    }

    public function test_converts_requests_to_strings(): void
    {
        $request = new Psr7\Request('PUT', 'http://foo.com/hi?123', [
            'Baz' => 'bar',
            'Qux' => 'ipsum',
        ], 'hello', '1.0');
        self::assertSame(
            "PUT /hi?123 HTTP/1.0\r\nHost: foo.com\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\Message::to_string($request)
        );
    }

    public function test_converts_responses_to_strings(): void
    {
        $response = new Psr7\Response(200, [
            'Baz' => 'bar',
            'Qux' => 'ipsum',
        ], 'hello', '1.0', 'FOO');
        self::assertSame(
            "HTTP/1.0 200 FOO\r\nBaz: bar\r\nQux: ipsum\r\n\r\nhello",
            Psr7\Message::to_string($response)
        );
    }

    public function test_correctly_renders_set_cookie_headers_to_string(): void
    {
        $response = new Psr7\Response(200, [
            'Set-Cookie' => ['bar', 'baz', 'qux'],
        ], 'hello', '1.0', 'FOO');
        self::assertSame(
            "HTTP/1.0 200 FOO\r\nSet-Cookie: bar\r\nSet-Cookie: baz\r\nSet-Cookie: qux\r\n\r\nhello",
            Psr7\Message::to_string($response)
        );
    }

    public function test_can_modify_request_with_uri(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modify_request($r1, [
            'uri' => new Psr7\Uri('http://www.foo.com'),
        ]);
        self::assertSame('http://www.foo.com', (string) $r2->getUri());
        self::assertSame('www.foo.com', (string) $r2->getHeaderLine('host'));
    }

    public function test_can_modify_request_with_uri_and_port(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com:8000');
        $r2 = Psr7\Utils::modify_request($r1, [
            'uri' => new Psr7\Uri('http://www.foo.com:8000'),
        ]);
        self::assertSame('http://www.foo.com:8000', (string) $r2->getUri());
        self::assertSame('www.foo.com:8000', (string) $r2->getHeaderLine('host'));
    }

    public function test_can_modify_request_with_case_insensitive_header(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com', ['User-Agent' => 'foo']);
        $r2 = Psr7\Utils::modify_request($r1, ['set_headers' => ['User-agent' => 'bar']]);
        self::assertSame('bar', $r2->getHeaderLine('User-Agent'));
        self::assertSame('bar', $r2->getHeaderLine('User-agent'));
    }

    public function test_returns_as_is_when_no_changes(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modify_request($r1, []);
        self::assertInstanceOf('Guzzle_Http\Psr7\Request', $r2);

        $r1 = new Psr7\Server_Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modify_request($r1, []);
        self::assertInstanceOf('Psr\Http\Message\ServerRequestInterface', $r2);
    }

    public function test_returns_uri_as_is_when_no_changes(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modify_request($r1, ['set_headers' => ['foo' => 'bar']]);
        self::assertNotSame($r1, $r2);
        self::assertSame('bar', $r2->getHeaderLine('foo'));
    }

    public function test_removes_headers_from_message(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com', ['foo' => 'bar']);
        $r2 = Psr7\Utils::modify_request($r1, ['remove_headers' => ['foo']]);
        self::assertNotSame($r1, $r2);
        self::assertFalse($r2->hasHeader('foo'));
    }

    public function test_adds_query_to_uri(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modify_request($r1, ['query' => 'foo=bar']);
        self::assertNotSame($r1, $r2);
        self::assertSame('foo=bar', $r2->getUri()->getQuery());
    }

    public function test_modify_request_keep_instance_of_request(): void
    {
        $r1 = new Psr7\Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modify_request($r1, ['remove_headers' => ['non-existent']]);
        self::assertInstanceOf('Guzzle_Http\Psr7\Request', $r2);

        $r1 = new Psr7\Server_Request('GET', 'http://foo.com');
        $r2 = Psr7\Utils::modify_request($r1, ['remove_headers' => ['non-existent']]);
        self::assertInstanceOf('Psr\Http\Message\ServerRequestInterface', $r2);
    }

    public function test_modify_server_request_with_uploaded_files(): void
    {
        $request = new Psr7\Server_Request('GET', 'http://example.com/bla');
        $file = new Psr7\Uploaded_File('Test', 100, \UPLOAD_ERR_OK);
        $request = $request->withUploadedFiles([$file]);

        /** @var Psr7\Server_Request $modifiedRequest */
        $modified_request = Psr7\Utils::modify_request($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertCount(1, $modified_request->getUploadedFiles());

        $files = $modified_request->getUploadedFiles();
        self::assertInstanceOf('Guzzle_Http\Psr7\Uploaded_File', $files[0]);
    }

    public function test_modify_server_request_with_cookies(): void
    {
        $request = (new Psr7\Server_Request('GET', 'http://example.com/bla'))
            ->withCookieParams(['name' => 'value']);

        /** @var Psr7\Server_Request $modifiedRequest */
        $modified_request = Psr7\Utils::modify_request($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modified_request->getCookieParams());
    }

    public function test_modify_server_request_parsed_body(): void
    {
        $request = (new Psr7\Server_Request('GET', 'http://example.com/bla'))
            ->withParsedBody(['name' => 'value']);

        /** @var Psr7\Server_Request $modifiedRequest */
        $modified_request = Psr7\Utils::modify_request($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modified_request->getParsedBody());
    }

    public function test_modify_server_request_query_params(): void
    {
        $request = (new Psr7\Server_Request('GET', 'http://example.com/bla'))
            ->withQueryParams(['name' => 'value']);

        /** @var Psr7\Server_Request $modifiedRequest */
        $modified_request = Psr7\Utils::modify_request($request, ['set_headers' => ['foo' => 'bar']]);

        self::assertSame(['name' => 'value'], $modified_request->getQueryParams());
    }

    public function test_modify_server_request_retains_attributes(): void
    {
        $request = (new Psr7\Server_Request('GET', 'http://example.com/bla'))
            ->withAttribute('foo', 'bar');

        /** @var Psr7\Server_Request $modifiedRequest */
        $modified_request = Psr7\Utils::modify_request($request, []);

        self::assertSame(['foo' => 'bar'], $modified_request->getAttributes());
    }

    /**
     * @return list<array{0: string[], 1: array, 2: array}>
     */
    public static function provides_caseless_remove_cases(): array
    {
        return [
            [
                ['foo-bar'],
                ['Foo-Bar' => 'hello'],
                [],
            ],
            [
                ['foo-bar'],
                ['hello'],
                ['hello'],
            ],
            [
                ['foo-Bar'],
                ['Foo-Bar' => 'hello', 123 => '', 'Foo-BAR' => 'hello123', 'foobar' => 'baz'],
                [123 => '', 'foobar' => 'baz'],
            ],
            [
                ['foo-Bar', 123],
                ['Foo-Bar' => 'hello', 123 => '', 'Foo-BAR' => 'hello123', 'foobar' => 'baz'],
                ['foobar' => 'baz'],
            ],
        ];
    }

    /**
     * @dataProvider provides_caseless_remove_cases
     *
     * @param string[] $keys
     */
    public function test_caseless_remove(array $keys, array $data, array $expected): void
    {
        self::assertSame($expected, Psr7\Utils::caseless_remove($keys, $data));
    }
}
