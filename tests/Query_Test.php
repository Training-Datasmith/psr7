<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use PHPUnit\Framework\TestCase;

class Query_Test extends TestCase
{
    public static function parse_query_provider()
    {
        return [
            // Does not need to parse when the string is empty
            ['', []],
            // Can parse mult-values items
            ['q=a&q=b', ['q' => ['a', 'b']]],
            // Can parse multi-valued items that use numeric indices
            ['q[0]=a&q[1]=b', ['q[0]' => 'a', 'q[1]' => 'b']],
            // Can parse duplicates and does not include numeric indices
            ['q[]=a&q[]=b', ['q[]' => ['a', 'b']]],
            // Ensures that the value of "q" is an array even though one value
            ['q[]=a', ['q[]' => 'a']],
            // Does not modify "." to "_" like PHP's parse_str()
            ['q.a=a&q.b=b', ['q.a' => 'a', 'q.b' => 'b']],
            // Can decode %20 to " "
            ['q%20a=a%20b', ['q a' => 'a b']],
            // Can parse funky strings with no values by assigning each to null
            ['q&a', ['q' => null, 'a' => null]],
            // Does not strip trailing equal signs
            ['data=abc=', ['data' => 'abc=']],
            // Can store duplicates without affecting other values
            ['foo=a&foo=b&?µ=c', ['foo' => ['a', 'b'], '?µ' => 'c']],
            // Sets value to null when no "=" is present
            ['foo', ['foo' => null]],
            // Preserves "0" keys.
            ['0', ['0' => null]],
            // Sets the value to an empty string when "=" is present
            ['0=', ['0' => '']],
            // Preserves falsey keys
            ['var=0', ['var' => '0']],
            ['a[b][c]=1&a[b][c]=2', ['a[b][c]' => ['1', '2']]],
            ['a[b]=c&a[d]=e', ['a[b]' => 'c', 'a[d]' => 'e']],
            // Ensure it doesn't leave things behind with repeated values
            // Can parse mult-values items
            ['q=a&q=b&q=c', ['q' => ['a', 'b', 'c']]],
            // Keeps first null when parsing mult-values
            ['q&q=&q=a', ['q' => [null, '', 'a']]],
        ];
    }

    /**
     * @dataProvider parse_query_provider
     */
    public function test_parses_queries($input, $output): void
    {
        $result = Psr7\Query::parse($input);
        self::assertSame($output, $result);
    }

    public function test_does_not_decode(): void
    {
        $str = 'foo%20=bar';
        $data = Psr7\Query::parse($str, false);
        self::assertSame(['foo%20' => 'bar'], $data);
    }

    /**
     * @dataProvider parse_query_provider
     */
    public function test_parses_and_builds_queries($input): void
    {
        $result = Psr7\Query::parse($input, false);
        self::assertSame($input, Psr7\Query::build($result, false));
    }

    public function test_encodes_with_rfc1738(): void
    {
        $str = Psr7\Query::build(['foo bar' => 'baz+'], PHP_QUERY_RFC1738);
        self::assertSame('foo+bar=baz%2B', $str);
    }

    public function test_encodes_with_rfc3986(): void
    {
        $str = Psr7\Query::build(['foo bar' => 'baz+'], PHP_QUERY_RFC3986);
        self::assertSame('foo%20bar=baz%2B', $str);
    }

    public function test_does_not_encode(): void
    {
        $str = Psr7\Query::build(['foo bar' => 'baz+'], false);
        self::assertSame('foo bar=baz+', $str);
    }

    public function test_can_control_decoding_type(): void
    {
        $result = Psr7\Query::parse('var=foo+bar', PHP_QUERY_RFC3986);
        self::assertSame('foo+bar', $result['var']);
        $result = Psr7\Query::parse('var=foo+bar', PHP_QUERY_RFC1738);
        self::assertSame('foo bar', $result['var']);
    }

    public function test_build_booleans(): void
    {
        $data = [
            'true' => true,
            'false' => false,
        ];
        self::assertEquals(http_build_query($data), Psr7\Query::build($data));

        $data = [
            'foo' => [true, 'true'],
            'bar' => [false, 'false'],
        ];
        self::assertEquals('foo=1&foo=true&bar=0&bar=false', Psr7\Query::build($data, PHP_QUERY_RFC1738));

        $data = [
            'foo' => true,
            'bar' => false,
        ];
        self::assertEquals('foo=true&bar=false', Psr7\Query::build($data, PHP_QUERY_RFC3986, false));
    }
}
