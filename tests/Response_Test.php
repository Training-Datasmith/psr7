<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use Guzzle_Http\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;

/**
 * @covers \Guzzle_Http\Psr7\Message_Trait
 * @covers \Guzzle_Http\Psr7\Response
 */
class Response_Test extends TestCase
{
    public function test_default_constructor(): void
    {
        $r = new Response();
        self::assertSame(200, $r->getStatusCode());
        self::assertSame('1.1', $r->getProtocolVersion());
        self::assertSame('OK', $r->getReasonPhrase());
        self::assertSame([], $r->getHeaders());
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('', (string) $r->getBody());
    }

    public function test_can_construct_with_status_code(): void
    {
        $r = new Response(404);
        self::assertSame(404, $r->getStatusCode());
        self::assertSame('Not Found', $r->getReasonPhrase());
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

        $r = new Response(200, [], $body);
        self::assertFalse($stream_is_read);
        self::assertSame($body, $r->getBody());
    }

    public function test_status_can_be_numeric_string(): void
    {
        $r = (new Response())->withStatus('201');

        self::assertSame(201, $r->getStatusCode());
        self::assertSame('Created', $r->getReasonPhrase());
    }

    public function test_can_construct_with_headers(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame('Bar', $r->getHeaderLine('Foo'));
        self::assertSame(['Bar'], $r->getHeader('Foo'));
    }

    public function test_can_construct_with_headers_as_array(): void
    {
        $r = new Response(200, [
            'Foo' => ['baz', 'bar'],
        ]);
        self::assertSame(['Foo' => ['baz', 'bar']], $r->getHeaders());
        self::assertSame('baz, bar', $r->getHeaderLine('Foo'));
        self::assertSame(['baz', 'bar'], $r->getHeader('Foo'));
    }

    public function test_can_construct_with_body(): void
    {
        $r = new Response(200, [], 'baz');
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('baz', (string) $r->getBody());
    }

    public function test_null_body(): void
    {
        $r = new Response(200, [], null);
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('', (string) $r->getBody());
    }

    public function test_falsey_body(): void
    {
        $r = new Response(200, [], '0');
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('0', (string) $r->getBody());
    }

    public function test_can_construct_with_reason(): void
    {
        $r = new Response(200, [], null, '1.1', 'bar');
        self::assertSame('bar', $r->getReasonPhrase());

        $r = new Response(200, [], null, '1.1', '0');
        self::assertSame('0', $r->getReasonPhrase(), 'Falsey reason works');
    }

    public function test_can_construct_with_protocol_version(): void
    {
        $r = new Response(200, [], null, '1000');
        self::assertSame('1000', $r->getProtocolVersion());
    }

    public function test_with_status_code_and_no_reason(): void
    {
        $r = (new Response())->withStatus(201);
        self::assertSame(201, $r->getStatusCode());
        self::assertSame('Created', $r->getReasonPhrase());
    }

    public function test_with_status_code_and_reason(): void
    {
        $r = (new Response())->withStatus(201, 'Foo');
        self::assertSame(201, $r->getStatusCode());
        self::assertSame('Foo', $r->getReasonPhrase());

        $r = (new Response())->withStatus(201, '0');
        self::assertSame(201, $r->getStatusCode());
        self::assertSame('0', $r->getReasonPhrase(), 'Falsey reason works');
    }

    public function test_with_protocol_version(): void
    {
        $r = (new Response())->withProtocolVersion('1000');
        self::assertSame('1000', $r->getProtocolVersion());
    }

    public function test_same_instance_when_same_protocol(): void
    {
        $r = new Response();
        self::assertSame($r, $r->withProtocolVersion('1.1'));
    }

    public function test_with_body(): void
    {
        $b = Psr7\Utils::stream_for('0');
        $r = (new Response())->withBody($b);
        self::assertInstanceOf(StreamInterface::class, $r->getBody());
        self::assertSame('0', (string) $r->getBody());
    }

    public function test_same_instance_when_same_body(): void
    {
        $r = new Response();
        $b = $r->getBody();
        self::assertSame($r, $r->withBody($b));
    }

    public function test_with_header(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('baZ', 'Bam');
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['Foo' => ['Bar'], 'baZ' => ['Bam']], $r2->getHeaders());
        self::assertSame('Bam', $r2->getHeaderLine('baz'));
        self::assertSame(['Bam'], $r2->getHeader('baz'));
    }

    public function test_numeric_header_value(): void
    {
        $r = (new Response())->withHeader('Api-Version', 1);
        self::assertSame(['Api-Version' => ['1']], $r->getHeaders());
    }

    public function test_with_header_as_array(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('baZ', ['Bam', 'Bar']);
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['Foo' => ['Bar'], 'baZ' => ['Bam', 'Bar']], $r2->getHeaders());
        self::assertSame('Bam, Bar', $r2->getHeaderLine('baz'));
        self::assertSame(['Bam', 'Bar'], $r2->getHeader('baz'));
    }

    public function test_with_header_replaces_different_case(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withHeader('foO', 'Bam');
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['foO' => ['Bam']], $r2->getHeaders());
        self::assertSame('Bam', $r2->getHeaderLine('foo'));
        self::assertSame(['Bam'], $r2->getHeader('foo'));
    }

    public function test_with_added_header(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('foO', 'Baz');
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['Foo' => ['Bar', 'Baz']], $r2->getHeaders());
        self::assertSame('Bar, Baz', $r2->getHeaderLine('foo'));
        self::assertSame(['Bar', 'Baz'], $r2->getHeader('foo'));
    }

    public function test_with_added_header_as_array(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('foO', ['Baz', 'Bam']);
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['Foo' => ['Bar', 'Baz', 'Bam']], $r2->getHeaders());
        self::assertSame('Bar, Baz, Bam', $r2->getHeaderLine('foo'));
        self::assertSame(['Bar', 'Baz', 'Bam'], $r2->getHeader('foo'));
    }

    public function test_with_added_header_that_does_not_exist(): void
    {
        $r = new Response(200, ['Foo' => 'Bar']);
        $r2 = $r->withAddedHeader('nEw', 'Baz');
        self::assertSame(['Foo' => ['Bar']], $r->getHeaders());
        self::assertSame(['Foo' => ['Bar'], 'nEw' => ['Baz']], $r2->getHeaders());
        self::assertSame('Baz', $r2->getHeaderLine('new'));
        self::assertSame(['Baz'], $r2->getHeader('new'));
    }

    public function test_without_header_that_exists(): void
    {
        $r = new Response(200, ['Foo' => 'Bar', 'Baz' => 'Bam']);
        $r2 = $r->withoutHeader('foO');
        self::assertTrue($r->hasHeader('foo'));
        self::assertSame(['Foo' => ['Bar'], 'Baz' => ['Bam']], $r->getHeaders());
        self::assertFalse($r2->hasHeader('foo'));
        self::assertSame(['Baz' => ['Bam']], $r2->getHeaders());
    }

    public function test_without_header_that_does_not_exist(): void
    {
        $r = new Response(200, ['Baz' => 'Bam']);
        $r2 = $r->withoutHeader('foO');
        self::assertSame($r, $r2);
        self::assertFalse($r2->hasHeader('foo'));
        self::assertSame(['Baz' => ['Bam']], $r2->getHeaders());
    }

    public function test_same_instance_when_removing_missing_header(): void
    {
        $r = new Response();
        self::assertSame($r, $r->withoutHeader('foo'));
    }

    public function test_pass_numeric_header_name_in_constructor(): void
    {
        $r = new Response(200, ['Location' => 'foo', '123' => 'bar']);
        self::assertSame('bar', $r->getHeaderLine('123'));
    }

    public function test_construct_response_empty_list_header_value(): void
    {
        $r = new Response(200, ['Foo' => []]);
        self::assertSame('', $r->getHeaderLine('Foo'));
        self::assertSame([], $r->getHeader('Foo'));
        self::assertSame(['Foo' => []], $r->getHeaders());
    }

    /**
     * @dataProvider invalid_header_provider
     */
    public function test_construct_response_invalid_header($header, $header_value, $expected_message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expected_message);
        new Response(200, [$header => $header_value]);
    }

    public static function invalid_header_provider(): iterable
    {
        return [
            ['', '', '"" is not valid header name'],
            ['foo', new \stdClass(),  'Header value must be scalar or null but stdClass provided.'],
        ];
    }

    /**
     * @dataProvider invalid_with_header_provider
     */
    public function test_with_invalid_header($header, $header_value, $expected_message): void
    {
        $r = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($expected_message);
        $r->withHeader($header, $header_value);
    }

    public static function invalid_with_header_provider(): iterable
    {
        yield from self::invalid_header_provider();
        yield [[], 'foo', 'Header name must be a string but array provided.'];
        yield [false, 'foo', 'Header name must be a string but boolean provided.'];
        yield [new \stdClass(), 'foo', 'Header name must be a string but stdClass provided.'];
        yield ['', 'foo', '"" is not valid header name.'];
        yield ["Content-Type\r\n\r\n", 'foo', "\"Content-Type\r\n\r\n\" is not valid header name."];
        yield ["Content-Type\r\n", 'foo', "\"Content-Type\r\n\" is not valid header name."];
        yield ["Content-Type\n", 'foo', "\"Content-Type\n\" is not valid header name."];
        yield ["\r\nContent-Type", 'foo', "\"\r\nContent-Type\" is not valid header name."];
        yield ["\nContent-Type", 'foo', "\"\nContent-Type\" is not valid header name."];
        yield ["\n", 'foo', "\"\n\" is not valid header name."];
        yield ["\r\n", 'foo', "\"\r\n\" is not valid header name."];
        yield ["\t", 'foo', "\"\t\" is not valid header name."];
    }

    public function test_header_values_are_trimmed(): void
    {
        $r1 = new Response(200, ['OWS' => " \t \tFoo\t \t "]);
        $r2 = (new Response())->withHeader('OWS', " \t \tFoo\t \t ");
        $r3 = (new Response())->withAddedHeader('OWS', " \t \tFoo\t \t ");

        foreach ([$r1, $r2, $r3] as $r) {
            self::assertSame(['OWS' => ['Foo']], $r->getHeaders());
            self::assertSame('Foo', $r->getHeaderLine('OWS'));
            self::assertSame(['Foo'], $r->getHeader('OWS'));
        }
    }

    public function test_with_added_header_array_value_and_keys(): void
    {
        $message = (new Response())->withAddedHeader('list', ['foo' => 'one']);
        $message = $message->withAddedHeader('list', ['foo' => 'two', 'bar' => 'three']);

        $header_line = $message->getHeaderLine('list');
        self::assertSame('one, two, three', $header_line);
    }

    /**
     * @dataProvider non_integer_status_code_provider
     *
     * @param mixed $invalidValues
     */
    public function test_construct_response_with_non_integer_status_code($invalid_values): void
    {
        $this->expectException(\TypeError::class);
        new Response($invalid_values);
    }

    /**
     * @dataProvider non_integer_status_code_provider
     *
     * @param mixed $invalidValues
     */
    public function test_response_change_status_code_with_non_integer($invalid_values): void
    {
        $response = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status code must be an integer value.');
        $response->withStatus($invalid_values);
    }

    public static function non_integer_status_code_provider(): iterable
    {
        return [
            ['whatever'],
            ['1.01'],
            [1.01],
            [new \stdClass()],
        ];
    }

    /**
     * @dataProvider invalid_status_code_range_provider
     *
     * @param mixed $invalidValues
     */
    public function test_construct_response_with_invalid_range_status_code($invalid_values): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status code must be an integer value between 1xx and 5xx.');
        new Response($invalid_values);
    }

    /**
     * @dataProvider invalid_status_code_range_provider
     *
     * @param mixed $invalidValues
     */
    public function test_response_change_status_code_with_with_invalid_range($invalid_values): void
    {
        $response = new Response();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Status code must be an integer value between 1xx and 5xx.');
        $response->withStatus($invalid_values);
    }

    public static function invalid_status_code_range_provider(): iterable
    {
        return [
            [600],
            [99],
        ];
    }
}
