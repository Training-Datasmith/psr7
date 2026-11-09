<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use Guzzle_Http\Psr7\Multipart_Stream;
use PHPUnit\Framework\TestCase;

class Multipart_Stream_Test extends TestCase
{
    public function test_creates_default_boundary(): void
    {
        $b = new Multipart_Stream();
        self::assertNotEmpty($b->get_boundary());
    }

    public function test_can_provide_boundary(): void
    {
        $b = new Multipart_Stream([], 'foo');
        self::assertSame('foo', $b->get_boundary());
    }

    public function test_is_not_writable(): void
    {
        $b = new Multipart_Stream();
        self::assertFalse($b->isWritable());
    }

    public function test_can_create_empty_stream(): void
    {
        $b = new Multipart_Stream();
        $boundary = $b->get_boundary();
        self::assertSame("--{$boundary}--\r\n", $b->getContents());
        self::assertSame(strlen($boundary) + 6, $b->getSize());
    }

    public function test_validates_files_array_element(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Multipart_Stream([['foo' => 'bar']]);
    }

    public function test_ensures_file_has_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Multipart_Stream([['contents' => 'bar']]);
    }

    public function test_throws_when_name_is_not_string_or_int(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'name' key must be a string or integer");

        new Multipart_Stream([
            [
                'name' => ['invalid'],
                'contents' => 'value',
            ],
        ]);
    }

    public function test_serializes_fields(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => 'foo',
                'contents' => 'bar',
            ],
            [
                'name' => 'baz',
                'contents' => 'bam',
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "bar\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"baz\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "bam\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_serializes_non_string_fields(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => 'int',
                'contents' => (int) 1,
            ],
            [
                'name' => 'bool',
                'contents' => (bool) false,
            ],
            [
                'name' => 'bool2',
                'contents' => (bool) true,
            ],
            [
                'name' => 'float',
                'contents' => (float) 1.1,
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"int\"\r\n",
            "Content-Length: 1\r\n",
            "\r\n",
            "1\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"bool\"\r\n",
            "\r\n",
            "\r\n",
            '--boundary',
            "\r\n",
            "Content-Disposition: form-data; name=\"bool2\"\r\n",
            "Content-Length: 1\r\n",
            "\r\n",
            "1\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"float\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "1.1\r\n",
            "--boundary--\r\n",
            '',
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_expands_nested_array_contents(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => 'foo',
                'contents' => [
                    ['key' => 'bar'],
                    ['key' => 'baz'],
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo[0][key]\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "bar\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo[1][key]\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "baz\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_expands_flat_array_contents(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => 'tags',
                'contents' => ['php', 'guzzle', 'psr7'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"tags[0]\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "php\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"tags[1]\"\r\n",
            "Content-Length: 6\r\n",
            "\r\n",
            "guzzle\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"tags[2]\"\r\n",
            "Content-Length: 4\r\n",
            "\r\n",
            "psr7\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_expands_associative_array_contents(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => 'user',
                'contents' => ['name' => 'John', 'email' => 'john@example.com'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"user[name]\"\r\n",
            "Content-Length: 4\r\n",
            "\r\n",
            "John\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"user[email]\"\r\n",
            "Content-Length: 16\r\n",
            "\r\n",
            "john@example.com\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_expands_deeply_nested_array_contents(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => 'data',
                'contents' => [
                    'level1' => [
                        'level2' => [
                            'level3' => 'deep',
                        ],
                    ],
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"data[level1][level2][level3]\"\r\n",
            "Content-Length: 4\r\n",
            "\r\n",
            "deep\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_expands_empty_array_contents(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => 'empty',
                'contents' => [],
            ],
        ], 'boundary');

        $expected = "--boundary--\r\n";

        self::assertSame($expected, (string) $b);
    }

    public function test_expands_array_contents_with_mixed_scalar_types(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => 'mixed',
                'contents' => [
                    'int' => 42,
                    'float' => 3.14,
                    'bool_true' => true,
                    'bool_false' => false,
                    'string' => 'hello',
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[int]\"\r\n",
            "Content-Length: 2\r\n",
            "\r\n",
            "42\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[float]\"\r\n",
            "Content-Length: 4\r\n",
            "\r\n",
            "3.14\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[bool_true]\"\r\n",
            "Content-Length: 1\r\n",
            "\r\n",
            "1\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[bool_false]\"\r\n",
            "\r\n",
            "\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"mixed[string]\"\r\n",
            "Content-Length: 5\r\n",
            "\r\n",
            "hello\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_expands_array_contents_with_numeric_string_keys(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => 'items',
                'contents' => ['10' => 'ten', '20' => 'twenty'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"items[10]\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "ten\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"items[20]\"\r\n",
            "Content-Length: 6\r\n",
            "\r\n",
            "twenty\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_throws_when_filename_used_with_array_contents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'filename' and 'headers' options cannot be used when 'contents' is an array");

        new Multipart_Stream([
            [
                'name' => 'foo',
                'contents' => ['bar' => 'baz'],
                'filename' => 'test.txt',
            ],
        ]);
    }

    public function test_throws_when_headers_used_with_array_contents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'filename' and 'headers' options cannot be used when 'contents' is an array");

        new Multipart_Stream([
            [
                'name' => 'foo',
                'contents' => ['bar' => 'baz'],
                'headers' => ['X-Custom' => 'value'],
            ],
        ]);
    }

    public function test_expands_array_contents_with_zero_name(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => '0',
                'contents' => ['a' => 'value'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"0[a]\"\r\n",
            "Content-Length: 5\r\n",
            "\r\n",
            "value\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_expands_array_contents_with_integer_name(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => 0,
                'contents' => ['a' => 'value'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"0[a]\"\r\n",
            "Content-Length: 5\r\n",
            "\r\n",
            "value\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_throws_when_zero_filename_used_with_array_contents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'filename' and 'headers' options cannot be used when 'contents' is an array");

        new Multipart_Stream([
            [
                'name' => 'foo',
                'contents' => ['bar' => 'baz'],
                'filename' => '0',
            ],
        ]);
    }

    public function test_expands_array_contents_with_nested_empty_branches(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => 'data',
                'contents' => ['a' => [], 'b' => 'value'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"data[b]\"\r\n",
            "Content-Length: 5\r\n",
            "\r\n",
            "value\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_expands_array_contents_with_empty_string_name(): void
    {
        $b = new Multipart_Stream([
            [
                'name' => '',
                'contents' => ['a' => 'value'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"[a]\"\r\n",
            "Content-Length: 5\r\n",
            "\r\n",
            "value\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_throws_when_null_filename_key_exists_with_array_contents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'filename' and 'headers' options cannot be used when 'contents' is an array");

        new Multipart_Stream([
            [
                'name' => 'foo',
                'contents' => ['bar' => 'baz'],
                'filename' => null,
            ],
        ]);
    }

    public function test_throws_when_empty_headers_key_exists_with_array_contents(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'filename' and 'headers' options cannot be used when 'contents' is an array");

        new Multipart_Stream([
            [
                'name' => 'foo',
                'contents' => ['bar' => 'baz'],
                'headers' => [],
            ],
        ]);
    }

    public function test_expands_array_contents_with_stream_leaves(): void
    {
        $file_stream = Psr7\Fn_Stream::decorate(Psr7\Utils::stream_for('file contents'), [
            'getMetadata' => static function (): string {
                return '/path/to/document.pdf';
            },
        ]);

        $b = new Multipart_Stream([
            [
                'name' => 'files',
                'contents' => [
                    'doc' => $file_stream,
                    'note' => 'plain text',
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"files[doc]\"; filename=\"document.pdf\"\r\n",
            "Content-Length: 13\r\n",
            "Content-Type: application/pdf\r\n",
            "\r\n",
            "file contents\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"files[note]\"\r\n",
            "Content-Length: 10\r\n",
            "\r\n",
            "plain text\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_serializes_files(): void
    {
        $f1 = Psr7\Fn_Stream::decorate(Psr7\Utils::stream_for('foo'), [
            'getMetadata' => static function (): string {
                return '/foo/bar.txt';
            },
        ]);

        $f2 = Psr7\Fn_Stream::decorate(Psr7\Utils::stream_for('baz'), [
            'getMetadata' => static function (): string {
                return '/foo/baz.jpg';
            },
        ]);

        $f3 = Psr7\Fn_Stream::decorate(Psr7\Utils::stream_for('bar'), [
            'getMetadata' => static function (): string {
                return '/foo/bar.unknown';
            },
        ]);

        $b = new Multipart_Stream([
            [
                'name' => 'foo',
                'contents' => $f1,
            ],
            [
                'name' => 'qux',
                'contents' => $f2,
            ],
            [
                'name' => 'qux',
                'contents' => $f3,
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo\"; filename=\"bar.txt\"\r\n",
            "Content-Length: 3\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "foo\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"qux\"; filename=\"baz.jpg\"\r\n",
            "Content-Length: 3\r\n",
            "Content-Type: image/jpeg\r\n",
            "\r\n",
            "baz\r\n",
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"qux\"; filename=\"bar.unknown\"\r\n",
            "Content-Length: 3\r\n",
            "Content-Type: application/octet-stream\r\n",
            "\r\n",
            "bar\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_serializes_files_with_mixed_newlines(): void
    {
        $content = "LF\nCRLF\r\nCR\r";
        $content_length = \strlen($content);

        $f1 = Psr7\Fn_Stream::decorate(Psr7\Utils::stream_for($content), [
            'getMetadata' => static function (): string {
                return '/foo/newlines.txt';
            },
        ]);

        $b = new Multipart_Stream([
            [
                'name' => 'newlines',
                'contents' => $f1,
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"newlines\"; filename=\"newlines.txt\"\r\n",
            "Content-Length: {$content_length}\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "{$content}\r\n",
            "--boundary--\r\n",
        ]);

        // Do not perform newline normalization in the assertion! The `$content` must
        // be embedded as-is in the payload.
        self::assertSame($expected, (string) $b);
    }

    public function test_serializes_files_with_custom_headers(): void
    {
        $f1 = Psr7\Fn_Stream::decorate(Psr7\Utils::stream_for('foo'), [
            'getMetadata' => static function (): string {
                return '/foo/bar.txt';
            },
        ]);

        $b = new Multipart_Stream([
            [
                'name' => 'foo',
                'contents' => $f1,
                'headers' => [
                    'x-foo' => 'bar',
                    'content-disposition' => 'custom',
                ],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "x-foo: bar\r\n",
            "content-disposition: custom\r\n",
            "Content-Length: 3\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "foo\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_serializes_files_with_custom_headers_and_multiple_values(): void
    {
        $f1 = Psr7\Fn_Stream::decorate(Psr7\Utils::stream_for('foo'), [
            'getMetadata' => static function (): string {
                return '/foo/bar.txt';
            },
        ]);

        $f2 = Psr7\Fn_Stream::decorate(Psr7\Utils::stream_for('baz'), [
            'getMetadata' => static function (): string {
                return '/foo/baz.jpg';
            },
        ]);

        $b = new Multipart_Stream([
            [
                'name' => 'foo',
                'contents' => $f1,
                'headers' => [
                    'x-foo' => 'bar',
                    'content-disposition' => 'custom',
                ],
            ],
            [
                'name' => 'foo',
                'contents' => $f2,
                'headers' => ['cOntenT-Type' => 'custom'],
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "x-foo: bar\r\n",
            "content-disposition: custom\r\n",
            "Content-Length: 3\r\n",
            "Content-Type: text/plain\r\n",
            "\r\n",
            "foo\r\n",
            "--boundary\r\n",
            "cOntenT-Type: custom\r\n",
            "Content-Disposition: form-data; name=\"foo\"; filename=\"baz.jpg\"\r\n",
            "Content-Length: 3\r\n",
            "\r\n",
            "baz\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $b);
    }

    public function test_can_create_with_none_metadata_stream_field(): void
    {
        $str = 'dummy text';
        $a = Psr7\Utils::stream_for(static function () use ($str): string {
            return $str;
        });
        $b = new Psr7\Limit_Stream($a, \strlen($str));
        $c = new Multipart_Stream([
            [
                'name' => 'foo',
                'contents' => $b,
            ],
        ], 'boundary');

        $expected = \implode('', [
            "--boundary\r\n",
            "Content-Disposition: form-data; name=\"foo\"\r\n",
            "\r\n",
            $str."\r\n",
            "--boundary--\r\n",
        ]);

        self::assertSame($expected, (string) $c);
    }
}
