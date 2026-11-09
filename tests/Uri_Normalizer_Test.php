<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7\Uri;
use Guzzle_Http\Psr7\Uri_Normalizer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

/**
 * @covers \Guzzle_Http\Psr7\Uri_Normalizer
 */
class Uri_Normalizer_Test extends TestCase
{
    public function test_capitalize_percent_encoding(): void
    {
        $actual_encoding = 'a%c2%7A%5eb%25%fa%fA%Fa';
        $expect_encoding = 'a%C2%7A%5Eb%25%FA%FA%FA';
        $uri = (new Uri())->withPath("/$actual_encoding")->withQuery($actual_encoding);

        self::assertSame("/$actual_encoding?$actual_encoding", (string) $uri, 'Not normalized automatically beforehand');

        $normalized_uri = Uri_Normalizer::normalize($uri, Uri_Normalizer::CAPITALIZE_PERCENT_ENCODING);

        self::assertInstanceOf(UriInterface::class, $normalized_uri);
        self::assertSame("/$expect_encoding?$expect_encoding", (string) $normalized_uri);
    }

    /**
     * @dataProvider get_unreserved_characters
     */
    public function test_decode_unreserved_characters(string $char): void
    {
        $percent_encoded = '%'.bin2hex($char);
        // Add encoded reserved characters to test that those are not decoded and include the percent-encoded
        // unreserved character both in lower and upper case to test the decoding is case-insensitive.
        $encoded_chars = $percent_encoded.'%2F%5B'.strtoupper($percent_encoded);
        $uri = (new Uri())->withPath("/$encoded_chars")->withQuery($encoded_chars);

        self::assertSame("/$encoded_chars?$encoded_chars", (string) $uri, 'Not normalized automatically beforehand');

        $normalized_uri = Uri_Normalizer::normalize($uri, Uri_Normalizer::DECODE_UNRESERVED_CHARACTERS);

        self::assertInstanceOf(UriInterface::class, $normalized_uri);
        self::assertSame("/$char%2F%5B$char?$char%2F%5B$char", (string) $normalized_uri);
    }

    public static function get_unreserved_characters(): iterable
    {
        $unreserved_chars = array_merge(range('a', 'z'), range('A', 'Z'), range(0, 9), ['-', '.', '_', '~']);

        return array_map(function ($char) {
            return [(string) $char];
        }, $unreserved_chars);
    }

    /**
     * @dataProvider get_empty_path_test_cases
     */
    public function test_convert_empty_path($uri, $expected): void
    {
        $normalized_uri = Uri_Normalizer::normalize(new Uri($uri), Uri_Normalizer::CONVERT_EMPTY_PATH);

        self::assertInstanceOf(UriInterface::class, $normalized_uri);
        self::assertSame($expected, (string) $normalized_uri);
    }

    public static function get_empty_path_test_cases(): iterable
    {
        return [
            ['http://example.org', 'http://example.org/'],
            ['https://example.org', 'https://example.org/'],
            ['urn://example.org', 'urn://example.org'],
        ];
    }

    public function test_remove_default_host(): void
    {
        $uri = new Uri('file://localhost/myfile');
        $normalized_uri = Uri_Normalizer::normalize($uri, Uri_Normalizer::REMOVE_DEFAULT_HOST);

        self::assertInstanceOf(UriInterface::class, $normalized_uri);
        self::assertSame('file:///myfile', (string) $normalized_uri);
    }

    public function test_remove_default_port(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::any())->method('getScheme')->willReturn('http');
        $uri->expects(self::any())->method('getPort')->willReturn(80);
        $uri->expects(self::once())->method('withPort')->with(null)->willReturn(new Uri('http://example.org'));

        $normalized_uri = Uri_Normalizer::normalize($uri, Uri_Normalizer::REMOVE_DEFAULT_PORT);

        self::assertInstanceOf(UriInterface::class, $normalized_uri);
        self::assertNull($normalized_uri->getPort());
    }

    public function test_remove_dot_segments(): void
    {
        $uri = new Uri('http://example.org/../a/b/../c/./d.html');
        $normalized_uri = Uri_Normalizer::normalize($uri, Uri_Normalizer::REMOVE_DOT_SEGMENTS);

        self::assertInstanceOf(UriInterface::class, $normalized_uri);
        self::assertSame('http://example.org/a/c/d.html', (string) $normalized_uri);
    }

    public function test_remove_dot_segments_of_absolute_path_reference(): void
    {
        $uri = new Uri('/../a/b/../c/./d.html');
        $normalized_uri = Uri_Normalizer::normalize($uri, Uri_Normalizer::REMOVE_DOT_SEGMENTS);

        self::assertInstanceOf(UriInterface::class, $normalized_uri);
        self::assertSame('/a/c/d.html', (string) $normalized_uri);
    }

    public function test_remove_dot_segments_of_relative_path_reference(): void
    {
        $uri = new Uri('../c/./d.html');
        $normalized_uri = Uri_Normalizer::normalize($uri, Uri_Normalizer::REMOVE_DOT_SEGMENTS);

        self::assertInstanceOf(UriInterface::class, $normalized_uri);
        self::assertSame('../c/./d.html', (string) $normalized_uri);
    }

    public function test_remove_duplicate_slashes(): void
    {
        $uri = new Uri('http://example.org//foo///bar/bam.html');
        $normalized_uri = Uri_Normalizer::normalize($uri, Uri_Normalizer::REMOVE_DUPLICATE_SLASHES);

        self::assertInstanceOf(UriInterface::class, $normalized_uri);
        self::assertSame('http://example.org/foo/bar/bam.html', (string) $normalized_uri);
    }

    public function test_sort_query_parameters(): void
    {
        $uri = new Uri('?lang=en&article=fred');
        $normalized_uri = Uri_Normalizer::normalize($uri, Uri_Normalizer::SORT_QUERY_PARAMETERS);

        self::assertInstanceOf(UriInterface::class, $normalized_uri);
        self::assertSame('?article=fred&lang=en', (string) $normalized_uri);
    }

    public function test_sort_query_parameters_with_same_keys(): void
    {
        $uri = new Uri('?a=b&b=c&a=a&a&b=a&b=b&a=d&a=c');
        $normalized_uri = Uri_Normalizer::normalize($uri, Uri_Normalizer::SORT_QUERY_PARAMETERS);

        self::assertInstanceOf(UriInterface::class, $normalized_uri);
        self::assertSame('?a&a=a&a=b&a=c&a=d&b=a&b=b&b=c', (string) $normalized_uri);
    }

    /**
     * @dataProvider get_equivalent_test_cases
     */
    public function test_is_equivalent(string $uri1, string $uri2, bool $expected): void
    {
        $equivalent = Uri_Normalizer::is_equivalent(new Uri($uri1), new Uri($uri2));

        self::assertSame($expected, $equivalent);
    }

    public static function get_equivalent_test_cases(): iterable
    {
        return [
            ['http://example.org', 'http://example.org', true],
            ['hTTp://eXaMpLe.org', 'http://example.org', true],
            ['http://example.org/path?#', 'http://example.org/path', true],
            ['http://example.org:80', 'http://example.org/', true],
            ['http://example.org/../a/.././p%61th?%7a=%5e', 'http://example.org/path?z=%5E', true],
            ['https://example.org/', 'http://example.org/', false],
            ['https://example.org/', '//example.org/', false],
            ['//example.org/', '//example.org/', true],
            ['file:/myfile', 'file:///myfile', true],
            ['file:///myfile', 'file://localhost/myfile', true],
        ];
    }
}
