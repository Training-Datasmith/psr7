<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use Guzzle_Http\Psr7\Request;
use Guzzle_Http\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \Guzzle_Http\Psr7\Message_Trait
 * @covers \Guzzle_Http\Psr7\Request
 */
class Request_Test extends TestCase
{
    public function test_request_uri_may_be_string(): void
    {
        $r = new Request('GET', '/');
        self::assertSame('/', (string) $r->getUri());
    }

    public function test_request_uri_may_be_uri(): void
    {
        $uri = new Uri('/');
        $r = new Request('GET', $uri);
        self::assertSame($uri, $r->getUri());
    }

    public function test_validate_request_uri(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Request('GET', '///');
    }

    public function test_can_construct_with_body(): void
    {
        $r = new Request('GET', '/', [], 'baz');
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('baz', (string) $r->getBody());
    }

    public function test_null_body(): void
    {
        $r = new Request('GET', '/', [], null);
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('', (string) $r->getBody());
    }

    public function test_falsey_body(): void
    {
        $r = new Request('GET', '/', [], '0');
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('0', (string) $r->getBody());
    }

    public function test_constructor_does_not_read_stream_body(): void
    {
        $stream_is_read = false;
        $body = Psr7\Fn_Stream::decorate(Psr7\Utils::stream_for(''), [
            '__toString' => function () use (&$stream_is_read) {
                $stream_is_read = true;

                return '';
            },
        ]);

        $r = new Request('GET', '/', [], $body);
        self::assertFalse($stream_is_read);
        self::assertSame($body, $r->getBody());
    }

    public function test_capitalizes_method(): void
    {
        $r = new Request('get', '/');
        self::assertSame('GET', $r->getMethod());
    }

    public function test_capitalizes_with_method(): void
    {
        $r = new Request('GET', '/');
        self::assertSame('PUT', $r->withMethod('put')->getMethod());
    }

    public function test_with_uri(): void
    {
        $r1 = new Request('GET', '/');
        $u1 = $r1->getUri();
        $u2 = new Uri('http://www.example.com');
        $r2 = $r1->withUri($u2);
        self::assertNotSame($r1, $r2);
        self::assertSame($u2, $r2->getUri());
        self::assertSame($u1, $r1->getUri());
    }

    /**
     * @dataProvider invalid_methods_provider
     */
    public function test_construct_with_invalid_methods($method): void
    {
        $this->expectException(\TypeError::class);
        new Request($method, '/');
    }

    /**
     * @dataProvider invalid_methods_provider
     */
    public function test_with_invalid_methods($method): void
    {
        $r = new Request('get', '/');
        $this->expectException(\InvalidArgumentException::class);
        $r->withMethod($method);
    }

    public static function invalid_methods_provider(): iterable
    {
        return [
            [null],
            [false],
            [['foo']],
            [new \stdClass()],
        ];
    }

    public function test_same_instance_when_same_uri(): void
    {
        $r1 = new Request('GET', 'http://foo.com');
        $r2 = $r1->withUri($r1->getUri());
        self::assertSame($r1, $r2);
    }

    public function test_with_request_target(): void
    {
        $r1 = new Request('GET', '/');
        $r2 = $r1->withRequestTarget('*');
        self::assertSame('*', $r2->getRequestTarget());
        self::assertSame('/', $r1->getRequestTarget());
    }

    public function test_request_target_does_not_allow_spaces(): void
    {
        $r1 = new Request('GET', '/');
        $this->expectException(\InvalidArgumentException::class);
        $r1->withRequestTarget('/foo bar');
    }

    public function test_request_target_defaults_to_slash(): void
    {
        $r1 = new Request('GET', '');
        self::assertSame('/', $r1->getRequestTarget());
        $r2 = new Request('GET', '*');
        self::assertSame('*', $r2->getRequestTarget());
        $r3 = new Request('GET', 'http://foo.com/bar baz/');
        self::assertSame('/bar%20baz/', $r3->getRequestTarget());
    }

    public function test_builds_request_target(): void
    {
        $r1 = new Request('GET', 'http://foo.com/baz?bar=bam');
        self::assertSame('/baz?bar=bam', $r1->getRequestTarget());
    }

    public function test_builds_request_target_with_falsey_query(): void
    {
        $r1 = new Request('GET', 'http://foo.com/baz?0');
        self::assertSame('/baz?0', $r1->getRequestTarget());
    }

    public function test_host_is_added_first(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', ['Foo' => 'Bar']);
        self::assertSame([
            'Host' => ['foo.com'],
            'Foo' => ['Bar'],
        ], $r->getHeaders());
    }

    public function test_header_value_with_whitespace(): void
    {
        $r = new Request('GET', 'https://example.com/', [
            'User-Agent' => 'Linux f0f489981e90 5.10.104-linuxkit 1 SMP Wed Mar 9 19:05:23 UTC 2022 x86_64',
        ]);
        self::assertSame([
            'Host' => ['example.com'],
            'User-Agent' => ['Linux f0f489981e90 5.10.104-linuxkit 1 SMP Wed Mar 9 19:05:23 UTC 2022 x86_64'],
        ], $r->getHeaders());
    }

    public function test_empty_list_header_value(): void
    {
        $r = new Request('GET', 'https://example.com/', ['Foo' => []]);
        self::assertSame('', $r->getHeaderLine('Foo'));
        self::assertSame([], $r->getHeader('Foo'));
        self::assertSame(['Host' => ['example.com'], 'Foo' => []], $r->getHeaders());
    }

    public function test_can_get_header_as_csv(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', [
            'Foo' => ['a', 'b', 'c'],
        ]);
        self::assertSame('a, b, c', $r->getHeaderLine('Foo'));
        self::assertSame('', $r->getHeaderLine('Bar'));
    }

    /**
     * @dataProvider provide_headers_containing_not_allowed_chars
     */
    public function test_contains_not_allowed_chars_on_header_field($header): void
    {
        $this->expectExceptionMessage(
            sprintf(
                '"%s" is not valid header name',
                $header
            )
        );
        $r = new Request(
            'GET',
            'http://foo.com/baz?bar=bam',
            [
                $header => 'value',
            ]
        );
    }

    public static function provide_headers_containing_not_allowed_chars(): iterable
    {
        return [[' key '], ['key '], [' key'], ['key/'], ['key('], ['key\\'], [' ']];
    }

    /**
     * @dataProvider provide_headers_contains_allowed_char
     */
    public function test_contains_allowed_chars_on_header_field($header): void
    {
        $r = new Request(
            'GET',
            'http://foo.com/baz?bar=bam',
            [
                $header => 'value',
            ]
        );
        self::assertArrayHasKey($header, $r->getHeaders());
    }

    public static function provide_headers_contains_allowed_char(): iterable
    {
        return [
            ['key'],
            ['key#'],
            ['key$'],
            ['key%'],
            ['key&'],
            ['key*'],
            ['key+'],
            ['key.'],
            ['key^'],
            ['key_'],
            ['key|'],
            ['key~'],
            ['key!'],
            ['key-'],
            ["key'"],
            ['key`'],
        ];
    }

    public function test_host_is_not_overwritten_when_preserving_host(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam', ['Host' => 'a.com']);
        self::assertSame(['Host' => ['a.com']], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.foo.com/bar'), true);
        self::assertSame('a.com', $r2->getHeaderLine('Host'));
    }

    public function test_with_uri_sets_host_if_not_set(): void
    {
        $r = (new Request('GET', 'http://foo.com/baz?bar=bam'))->withoutHeader('Host');
        self::assertSame([], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.baz.com/bar'), true);
        self::assertSame('www.baz.com', $r2->getHeaderLine('Host'));
    }

    public function test_overrides_host_with_uri(): void
    {
        $r = new Request('GET', 'http://foo.com/baz?bar=bam');
        self::assertSame(['Host' => ['foo.com']], $r->getHeaders());
        $r2 = $r->withUri(new Uri('http://www.baz.com/bar'));
        self::assertSame('www.baz.com', $r2->getHeaderLine('Host'));
    }

    public function test_aggregates_headers(): void
    {
        $r = new Request('GET', '', [
            'ZOO' => 'zoobar',
            'zoo' => ['foobar', 'zoobar'],
        ]);
        self::assertSame(['ZOO' => ['zoobar', 'foobar', 'zoobar']], $r->getHeaders());
        self::assertSame('zoobar, foobar, zoobar', $r->getHeaderLine('zoo'));
    }

    public function test_adds_port_to_header(): void
    {
        $r = new Request('GET', 'http://foo.com:8124/bar');
        self::assertSame('foo.com:8124', $r->getHeaderLine('host'));
    }

    public function test_adds_port_to_header_and_replace_previous_port(): void
    {
        $r = new Request('GET', 'http://foo.com:8124/bar');
        $r = $r->withUri(new Uri('http://foo.com:8125/bar'));
        self::assertSame('foo.com:8125', $r->getHeaderLine('host'));
    }

    /**
     * @dataProvider provide_header_values_containing_not_allowed_chars
     */
    public function test_contains_not_allowed_chars_on_header_value(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('"%s" is not valid header value', $value));

        $r = new Request(
            'GET',
            'http://foo.com/baz?bar=bam',
            [
                'testing' => $value,
            ]
        );
    }

    public static function provide_header_values_containing_not_allowed_chars(): iterable
    {
        // Explicit tests for newlines as the most common exploit vector.
        $tests = [
            ["new\nline"],
            ["new\r\nline"],
            ["new\rline"],
            // Line folding is technically allowed, but deprecated.
            // We don't support it.
            ["new\r\n line"],
            ["newline\n"],
            ["\nnewline"],
            ["newline\r\n"],
            ["\r\nnewline"],
        ];

        for ($i = 0; $i <= 0xFF; ++$i) {
            if (\chr($i) == "\t") {
                continue;
            }
            if (\chr($i) == ' ') {
                continue;
            }
            if ($i >= 0x21 && $i <= 0x7E) {
                continue;
            }
            if ($i >= 0x80) {
                continue;
            }

            $tests[] = ['foo'.\chr($i).'bar'];
            $tests[] = ['foo'.\chr($i)];
        }

        return $tests;
    }
}
