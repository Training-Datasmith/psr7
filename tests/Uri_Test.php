<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7\Exception\Malformed_Uri_Exception;
use Guzzle_Http\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

/**
 * @covers \Guzzle_Http\Psr7\Uri
 */
class Uri_Test extends TestCase
{
    public function test_parses_provided_uri(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path/123?q=abc#test');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass@example.com:8080', $uri->getAuthority());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('/path/123', $uri->getPath());
        self::assertSame('q=abc', $uri->getQuery());
        self::assertSame('test', $uri->getFragment());
        self::assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string) $uri);
    }

    public function test_can_transform_and_retrieve_parts_individually(): void
    {
        $uri = (new Uri())
            ->withScheme('https')
            ->withUserInfo('user', 'pass')
            ->withHost('example.com')
            ->withPort(8080)
            ->withPath('/path/123')
            ->withQuery('q=abc')
            ->withFragment('test');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass@example.com:8080', $uri->getAuthority());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('/path/123', $uri->getPath());
        self::assertSame('q=abc', $uri->getQuery());
        self::assertSame('test', $uri->getFragment());
        self::assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string) $uri);
    }

    /**
     * @dataProvider get_valid_uris
     */
    public function test_valid_uris_stay_valid(string $input): void
    {
        $uri = new Uri($input);

        self::assertSame($input, (string) $uri);
    }

    /**
     * @dataProvider get_valid_uris
     */
    public function test_from_parts(string $input): void
    {
        $uri = Uri::from_parts(parse_url($input));

        self::assertSame($input, (string) $uri);
    }

    public static function get_valid_uris(): iterable
    {
        return [
            ['urn:path-rootless'],
            ['urn:path:with:colon'],
            ['urn:/path-absolute'],
            ['urn:/'],
            // only scheme with empty path
            ['urn:'],
            // only path
            ['/'],
            ['relative/'],
            ['0'],
            // same document reference
            [''],
            // network path without scheme
            ['//example.org'],
            ['//example.org/'],
            ['//example.org?q#h'],
            // only query
            ['?q'],
            ['?q=abc&foo=bar'],
            // only fragment
            ['#fragment'],
            // dot segments are not removed automatically
            ['./foo/../bar'],
        ];
    }

    /**
     * @dataProvider get_invalid_uris
     */
    public function test_invalid_uris_throw_exception(string $invalid_uri): void
    {
        $this->expectException(Malformed_Uri_Exception::class);
        new Uri($invalid_uri);
    }

    public static function get_invalid_uris(): iterable
    {
        return [
            // parse_url() requires the host component which makes sense for http(s)
            // but not when the scheme is not known or different. So '//' or '///' is
            // currently invalid as well but should not according to RFC 3986.
            ['http://'],
            ['urn://host:with:colon'], // host cannot contain ":"
        ];
    }

    public function test_port_must_be_valid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port: 100000. Must be between 0 and 65535');
        (new Uri())->withPort(100000);
    }

    public function test_with_port_cannot_be_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid port: -1. Must be between 0 and 65535');
        (new Uri())->withPort(-1);
    }

    public function test_parse_uri_port_cannot_be_negative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to parse URI');
        new Uri('//example.com:-1');
    }

    public function test_scheme_must_have_correct_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withScheme([]);
    }

    public function test_host_must_have_correct_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withHost([]);
    }

    public function test_path_must_have_correct_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withPath([]);
    }

    public function test_query_must_have_correct_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withQuery([]);
    }

    public function test_fragment_must_have_correct_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new Uri())->withFragment([]);
    }

    public function test_can_parse_falsey_uri_parts(): void
    {
        $uri = new Uri('0://0:0@0/0?0#0');

        self::assertSame('0', $uri->getScheme());
        self::assertSame('0:0@0', $uri->getAuthority());
        self::assertSame('0:0', $uri->getUserInfo());
        self::assertSame('0', $uri->getHost());
        self::assertSame('/0', $uri->getPath());
        self::assertSame('0', $uri->getQuery());
        self::assertSame('0', $uri->getFragment());
        self::assertSame('0://0:0@0/0?0#0', (string) $uri);
    }

    public function test_can_construct_falsey_uri_parts(): void
    {
        $uri = (new Uri())
            ->withScheme('0')
            ->withUserInfo('0', '0')
            ->withHost('0')
            ->withPath('/0')
            ->withQuery('0')
            ->withFragment('0');

        self::assertSame('0', $uri->getScheme());
        self::assertSame('0:0@0', $uri->getAuthority());
        self::assertSame('0:0', $uri->getUserInfo());
        self::assertSame('0', $uri->getHost());
        self::assertSame('/0', $uri->getPath());
        self::assertSame('0', $uri->getQuery());
        self::assertSame('0', $uri->getFragment());
        self::assertSame('0://0:0@0/0?0#0', (string) $uri);
    }

    /**
     * @dataProvider get_port_test_cases
     */
    public function test_is_default_port(string $scheme, ?int $port, bool $is_default_port): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->expects(self::any())->method('getScheme')->willReturn($scheme);
        $uri->expects(self::any())->method('getPort')->willReturn($port);

        self::assertSame($is_default_port, Uri::is_default_port($uri));
    }

    public static function get_port_test_cases(): iterable
    {
        return [
            ['http', null, true],
            ['http', 80, true],
            ['http', 8080, false],
            ['https', null, true],
            ['https', 443, true],
            ['https', 444, false],
            ['ftp', 21, true],
            ['gopher', 70, true],
            ['nntp', 119, true],
            ['news', 119, true],
            ['telnet', 23, true],
            ['tn3270', 23, true],
            ['imap', 143, true],
            ['pop', 110, true],
            ['ldap', 389, true],
        ];
    }

    public function test_is_absolute(): void
    {
        self::assertTrue(Uri::is_absolute(new Uri('http://example.org')));
        self::assertFalse(Uri::is_absolute(new Uri('//example.org')));
        self::assertFalse(Uri::is_absolute(new Uri('/abs-path')));
        self::assertFalse(Uri::is_absolute(new Uri('rel-path')));
    }

    public function test_is_network_path_reference(): void
    {
        self::assertFalse(Uri::is_network_path_reference(new Uri('http://example.org')));
        self::assertTrue(Uri::is_network_path_reference(new Uri('//example.org')));
        self::assertFalse(Uri::is_network_path_reference(new Uri('/abs-path')));
        self::assertFalse(Uri::is_network_path_reference(new Uri('rel-path')));
    }

    public function test_is_absolute_path_reference(): void
    {
        self::assertFalse(Uri::is_absolute_path_reference(new Uri('http://example.org')));
        self::assertFalse(Uri::is_absolute_path_reference(new Uri('//example.org')));
        self::assertTrue(Uri::is_absolute_path_reference(new Uri('/abs-path')));
        self::assertTrue(Uri::is_absolute_path_reference(new Uri('/')));
        self::assertFalse(Uri::is_absolute_path_reference(new Uri('rel-path')));
    }

    public function test_is_relative_path_reference(): void
    {
        self::assertFalse(Uri::is_relative_path_reference(new Uri('http://example.org')));
        self::assertFalse(Uri::is_relative_path_reference(new Uri('//example.org')));
        self::assertFalse(Uri::is_relative_path_reference(new Uri('/abs-path')));
        self::assertTrue(Uri::is_relative_path_reference(new Uri('rel-path')));
        self::assertTrue(Uri::is_relative_path_reference(new Uri('')));
    }

    public function test_is_same_document_reference(): void
    {
        self::assertFalse(Uri::is_same_document_reference(new Uri('http://example.org')));
        self::assertFalse(Uri::is_same_document_reference(new Uri('//example.org')));
        self::assertFalse(Uri::is_same_document_reference(new Uri('/abs-path')));
        self::assertFalse(Uri::is_same_document_reference(new Uri('rel-path')));
        self::assertFalse(Uri::is_same_document_reference(new Uri('?query')));
        self::assertTrue(Uri::is_same_document_reference(new Uri('')));
        self::assertTrue(Uri::is_same_document_reference(new Uri('#fragment')));

        $base_uri = new Uri('http://example.org/path?foo=bar');

        self::assertTrue(Uri::is_same_document_reference(new Uri('#fragment'), $base_uri));
        self::assertTrue(Uri::is_same_document_reference(new Uri('?foo=bar#fragment'), $base_uri));
        self::assertTrue(Uri::is_same_document_reference(new Uri('/path?foo=bar#fragment'), $base_uri));
        self::assertTrue(Uri::is_same_document_reference(new Uri('path?foo=bar#fragment'), $base_uri));
        self::assertTrue(Uri::is_same_document_reference(new Uri('//example.org/path?foo=bar#fragment'), $base_uri));
        self::assertTrue(Uri::is_same_document_reference(new Uri('http://example.org/path?foo=bar#fragment'), $base_uri));

        self::assertFalse(Uri::is_same_document_reference(new Uri('https://example.org/path?foo=bar'), $base_uri));
        self::assertFalse(Uri::is_same_document_reference(new Uri('http://example.com/path?foo=bar'), $base_uri));
        self::assertFalse(Uri::is_same_document_reference(new Uri('http://example.org/'), $base_uri));
        self::assertFalse(Uri::is_same_document_reference(new Uri('http://example.org'), $base_uri));

        self::assertFalse(Uri::is_same_document_reference(new Uri('urn:/path'), new Uri('urn://example.com/path')));
    }

    public function test_add_and_remove_query_values(): void
    {
        $uri = new Uri();
        $uri = Uri::with_query_value($uri, 'a', 'b');
        $uri = Uri::with_query_value($uri, 'c', 'd');
        $uri = Uri::with_query_value($uri, 'e', null);
        self::assertSame('a=b&c=d&e', $uri->getQuery());

        $uri = Uri::without_query_value($uri, 'c');
        self::assertSame('a=b&e', $uri->getQuery());
        $uri = Uri::without_query_value($uri, 'e');
        self::assertSame('a=b', $uri->getQuery());
        $uri = Uri::without_query_value($uri, 'a');
        self::assertSame('', $uri->getQuery());
    }

    public function test_scalar_query_values(): void
    {
        $uri = new Uri();
        $uri = Uri::with_query_values($uri, [
            2 => 2,
            1 => true,
            'false' => false,
            'float' => 3.1,
        ]);

        self::assertSame('2=2&1=1&false=&float=3.1', $uri->getQuery());
    }

    public function test_with_query_values(): void
    {
        $uri = new Uri();
        $uri = Uri::with_query_values($uri, [
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        self::assertSame('key1=value1&key2=value2', $uri->getQuery());
    }

    public function test_with_query_values_replaces_same_keys(): void
    {
        $uri = new Uri();

        $uri = Uri::with_query_values($uri, [
            'key1' => 'value1',
            'key2' => 'value2',
        ]);

        $uri = Uri::with_query_values($uri, [
            'key2' => 'newvalue',
        ]);

        self::assertSame('key1=value1&key2=newvalue', $uri->getQuery());
    }

    public function test_with_query_value_replaces_same_keys(): void
    {
        $uri = new Uri();
        $uri = Uri::with_query_value($uri, 'a', 'b');
        $uri = Uri::with_query_value($uri, 'c', 'd');
        $uri = Uri::with_query_value($uri, 'a', 'e');
        self::assertSame('c=d&a=e', $uri->getQuery());
    }

    public function test_without_query_value_removes_all_same_keys(): void
    {
        $uri = (new Uri())->withQuery('a=b&c=d&a=e');
        $uri = Uri::without_query_value($uri, 'a');
        self::assertSame('c=d', $uri->getQuery());
    }

    public function test_remove_non_existing_query_value(): void
    {
        $uri = new Uri();
        $uri = Uri::with_query_value($uri, 'a', 'b');
        $uri = Uri::without_query_value($uri, 'c');
        self::assertSame('a=b', $uri->getQuery());
    }

    public function test_with_query_value_handles_encoding(): void
    {
        $uri = new Uri();
        $uri = Uri::with_query_value($uri, 'E=mc^2', 'ein&stein');
        self::assertSame('E%3Dmc%5E2=ein%26stein', $uri->getQuery(), 'Decoded key/value get encoded');

        $uri = new Uri();
        $uri = Uri::with_query_value($uri, 'E%3Dmc%5e2', 'ein%26stein');
        self::assertSame('E%3Dmc%5e2=ein%26stein', $uri->getQuery(), 'Encoded key/value do not get double-encoded');
    }

    public function test_with_query_value_encodes_plus_sign(): void
    {
        $uri = new Uri();
        $uri = Uri::with_query_value($uri, 'a+b', 'c+d');
        self::assertSame('a%2Bb=c%2Bd', $uri->getQuery(), 'Plus signs in key and value get encoded to %2B');

        $uri = new Uri();
        $uri = Uri::with_query_value($uri, 'query', 'a+b c');
        self::assertSame('query=a%2Bb%20c', $uri->getQuery(), 'Plus sign is encoded distinctly from space');
    }

    public function test_without_query_value_handles_encoding(): void
    {
        // It also tests that the case of the percent-encoding does not matter,
        // i.e. both lowercase "%3d" and uppercase "%5E" can be removed.
        $uri = (new Uri())->withQuery('E%3dmc%5E2=einstein&foo=bar');
        $uri = Uri::without_query_value($uri, 'E=mc^2');
        self::assertSame('foo=bar', $uri->getQuery(), 'Handles key in decoded form');

        $uri = (new Uri())->withQuery('E%3dmc%5E2=einstein&foo=bar');
        $uri = Uri::without_query_value($uri, 'E%3Dmc%5e2');
        self::assertSame('foo=bar', $uri->getQuery(), 'Handles key in encoded form');
    }

    public function test_scheme_is_normalized_to_lowercase(): void
    {
        $uri = new Uri('HTTP://example.com');

        self::assertSame('http', $uri->getScheme());
        self::assertSame('http://example.com', (string) $uri);

        $uri = (new Uri('//example.com'))->withScheme('HTTP');

        self::assertSame('http', $uri->getScheme());
        self::assertSame('http://example.com', (string) $uri);
    }

    public function test_host_is_normalized_to_lowercase(): void
    {
        $uri = new Uri('//eXaMpLe.CoM');

        self::assertSame('example.com', $uri->getHost());
        self::assertSame('//example.com', (string) $uri);

        $uri = (new Uri())->withHost('eXaMpLe.CoM');

        self::assertSame('example.com', $uri->getHost());
        self::assertSame('//example.com', (string) $uri);
    }

    public function test_port_is_null_if_standard_port_for_scheme(): void
    {
        // HTTPS standard port
        $uri = new Uri('https://example.com:443');
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('https://example.com'))->withPort(443);
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());

        // HTTP standard port
        $uri = new Uri('http://example.com:80');
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());

        $uri = (new Uri('http://example.com'))->withPort(80);
        self::assertNull($uri->getPort());
        self::assertSame('example.com', $uri->getAuthority());
    }

    public function test_port_is_returned_if_scheme_unknown(): void
    {
        $uri = (new Uri('//example.com'))->withPort(80);

        self::assertSame(80, $uri->getPort());
        self::assertSame('example.com:80', $uri->getAuthority());
    }

    public function test_standard_port_is_null_if_scheme_changes(): void
    {
        $uri = new Uri('http://example.com:443');
        self::assertSame('http', $uri->getScheme());
        self::assertSame(443, $uri->getPort());

        $uri = $uri->withScheme('https');
        self::assertNull($uri->getPort());
    }

    public function test_port_passed_as_string_is_casted_to_int(): void
    {
        $uri = (new Uri('//example.com'))->withPort('8080');

        self::assertSame(8080, $uri->getPort(), 'Port is returned as integer');
        self::assertSame('example.com:8080', $uri->getAuthority());
    }

    public function test_port_can_be_removed(): void
    {
        $uri = (new Uri('http://example.com:8080'))->withPort(null);

        self::assertNull($uri->getPort());
        self::assertSame('http://example.com', (string) $uri);
    }

    /**
     * In RFC 8986 the host is optional and the authority can only
     * consist of the user info and port.
     */
    public function test_authority_with_user_info_or_port_but_without_host(): void
    {
        $uri = (new Uri())->withUserInfo('user', 'pass');

        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('user:pass@', $uri->getAuthority());

        $uri = $uri->withPort(8080);
        self::assertSame(8080, $uri->getPort());
        self::assertSame('user:pass@:8080', $uri->getAuthority());
        self::assertSame('//user:pass@:8080', (string) $uri);

        $uri = $uri->withUserInfo('');
        self::assertSame(':8080', $uri->getAuthority());
    }

    public function test_host_in_http_uri_defaults_to_localhost(): void
    {
        $uri = (new Uri())->withScheme('http');

        self::assertSame('localhost', $uri->getHost());
        self::assertSame('localhost', $uri->getAuthority());
        self::assertSame('http://localhost', (string) $uri);
    }

    public function test_host_in_https_uri_defaults_to_localhost(): void
    {
        $uri = (new Uri())->withScheme('https');

        self::assertSame('localhost', $uri->getHost());
        self::assertSame('localhost', $uri->getAuthority());
        self::assertSame('https://localhost', (string) $uri);
    }

    public function test_file_scheme_with_empty_host_reconstruction(): void
    {
        $uri = new Uri('file:///tmp/filename.ext');

        self::assertSame('', $uri->getHost());
        self::assertSame('', $uri->getAuthority());
        self::assertSame('file:///tmp/filename.ext', (string) $uri);
    }

    public static function uri_components_encoding_provider(): iterable
    {
        $unreserved = 'a-zA-Z0-9.-_~!$&\'()*+,;=:@';

        return [
            // Percent encode spaces
            ['/pa th?q=va lue#frag ment', '/pa%20th', 'q=va%20lue', 'frag%20ment', '/pa%20th?q=va%20lue#frag%20ment'],
            // Percent encode multibyte
            ['/€?€#€', '/%E2%82%AC', '%E2%82%AC', '%E2%82%AC', '/%E2%82%AC?%E2%82%AC#%E2%82%AC'],
            // Don't encode something that's already encoded
            ['/pa%20th?q=va%20lue#frag%20ment', '/pa%20th', 'q=va%20lue', 'frag%20ment', '/pa%20th?q=va%20lue#frag%20ment'],
            // Percent encode invalid percent encodings
            ['/pa%2-th?q=va%2-lue#frag%2-ment', '/pa%252-th', 'q=va%252-lue', 'frag%252-ment', '/pa%252-th?q=va%252-lue#frag%252-ment'],
            // Don't encode path segments
            ['/pa/th//two?q=va/lue#frag/ment', '/pa/th//two', 'q=va/lue', 'frag/ment', '/pa/th//two?q=va/lue#frag/ment'],
            // Don't encode unreserved chars or sub-delimiters
            ["/$unreserved?$unreserved#$unreserved", "/$unreserved", $unreserved, $unreserved, "/$unreserved?$unreserved#$unreserved"],
            // Encoded unreserved chars are not decoded
            ['/p%61th?q=v%61lue#fr%61gment', '/p%61th', 'q=v%61lue', 'fr%61gment', '/p%61th?q=v%61lue#fr%61gment'],
        ];
    }

    /**
     * @dataProvider uri_components_encoding_provider
     */
    public function test_uri_components_get_encoded_properly(string $input, string $path, string $query, string $fragment, string $output): void
    {
        $uri = new Uri($input);
        self::assertSame($path, $uri->getPath());
        self::assertSame($query, $uri->getQuery());
        self::assertSame($fragment, $uri->getFragment());
        self::assertSame($output, (string) $uri);
    }

    public function test_with_path_encodes_properly(): void
    {
        $uri = (new Uri())->withPath('/baz?#€/b%61r');
        // Query and fragment delimiters and multibyte chars are encoded.
        self::assertSame('/baz%3F%23%E2%82%AC/b%61r', $uri->getPath());
        self::assertSame('/baz%3F%23%E2%82%AC/b%61r', (string) $uri);
    }

    public function test_with_query_encodes_properly(): void
    {
        $uri = (new Uri())->withQuery('?=#&€=/&b%61r');
        // A query starting with a "?" is valid and must not be magically removed. Otherwise it would be impossible to
        // construct such an URI. Also the "?" and "/" does not need to be encoded in the query.
        self::assertSame('?=%23&%E2%82%AC=/&b%61r', $uri->getQuery());
        self::assertSame('??=%23&%E2%82%AC=/&b%61r', (string) $uri);
    }

    public function test_with_fragment_encodes_properly(): void
    {
        $uri = (new Uri())->withFragment('#€?/b%61r');
        // A fragment starting with a "#" is valid and must not be magically removed. Otherwise it would be impossible to
        // construct such an URI. Also the "?" and "/" does not need to be encoded in the fragment.
        self::assertSame('%23%E2%82%AC?/b%61r', $uri->getFragment());
        self::assertSame('#%23%E2%82%AC?/b%61r', (string) $uri);
    }

    public function test_allows_for_relative_uri(): void
    {
        $uri = (new Uri())->withPath('foo');
        self::assertSame('foo', $uri->getPath());
        self::assertSame('foo', (string) $uri);
    }

    public function test_relative_path_and_authority(): void
    {
        $uri = (new Uri())->withHost('example.com')->withPath('foo');
        self::assertSame('foo', $uri->getPath());
        self::assertSame('//example.com/foo', $uri->__toString());
    }

    public function test_path_starting_with_two_slashes_and_no_authority_is_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The path of a URI without an authority must not start with two slashes "//"');
        // URI "//foo" would be interpreted as network reference and thus change the original path to the host
        (new Uri())->withPath('//foo');
    }

    public function test_path_starting_with_two_slashes(): void
    {
        $uri = new Uri('http://example.org//path-not-host.com');
        self::assertSame('//path-not-host.com', $uri->getPath());

        $uri = $uri->withScheme('');
        self::assertSame('//example.org//path-not-host.com', (string) $uri); // This is still valid
        $this->expectException(\InvalidArgumentException::class);
        $uri->withHost(''); // Now it becomes invalid
    }

    public function test_relative_uri_with_path_beginng_with_colon_segment_is_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A relative URI must not have a path beginning with a segment containing a colon');
        (new Uri())->withPath('mailto:foo');
    }

    public function test_relative_uri_with_path_having_colon_segment(): void
    {
        $uri = (new Uri('urn:/mailto:foo'))->withScheme('');
        self::assertSame('/mailto:foo', $uri->getPath());

        $this->expectException(\InvalidArgumentException::class);
        (new Uri('urn:mailto:foo'))->withScheme('');
    }

    public function test_default_return_values_of_getters(): void
    {
        $uri = new Uri();

        self::assertSame('', $uri->getScheme());
        self::assertSame('', $uri->getAuthority());
        self::assertSame('', $uri->getUserInfo());
        self::assertSame('', $uri->getHost());
        self::assertNull($uri->getPort());
        self::assertSame('', $uri->getPath());
        self::assertSame('', $uri->getQuery());
        self::assertSame('', $uri->getFragment());
    }

    public function test_immutability(): void
    {
        $uri = new Uri();

        self::assertNotSame($uri, $uri->withScheme('https'));
        self::assertNotSame($uri, $uri->withUserInfo('user', 'pass'));
        self::assertNotSame($uri, $uri->withHost('example.com'));
        self::assertNotSame($uri, $uri->withPort(8080));
        self::assertNotSame($uri, $uri->withPath('/path/123'));
        self::assertNotSame($uri, $uri->withQuery('q=abc'));
        self::assertNotSame($uri, $uri->withFragment('test'));
    }

    public function test_extending_classes_instantiates(): void
    {
        // The non-standard port triggers a cascade of private methods which
        // should not use late static binding to access private static members.
        // If they do, this will fatal.
        self::assertInstanceOf(
            Extended_Uri_Test::class,
            new Extended_Uri_Test('http://h:9/')
        );
    }

    public function test_special_chars_of_user_info(): void
    {
        // The `userInfo` must always be URL-encoded.
        $uri = (new Uri())->withUserInfo('foo@bar.com', 'pass#word');
        self::assertSame('foo%40bar.com:pass%23word', $uri->getUserInfo());

        // The `userInfo` can already be URL-encoded: it should not be encoded twice.
        $uri = (new Uri())->withUserInfo('foo%40bar.com', 'pass%23word');
        self::assertSame('foo%40bar.com:pass%23word', $uri->getUserInfo());
    }

    public function test_internationalized_domain_name(): void
    {
        $uri = new Uri('https://яндекс.рф');
        self::assertSame('яндекс.рф', $uri->getHost());

        $uri = new Uri('https://яндекAс.рф');
        self::assertSame('яндекaс.рф', $uri->getHost());
    }

    public function test_i_pv6host(): void
    {
        $uri = new Uri('https://[2a00:f48:1008::212:183:10]');
        self::assertSame('[2a00:f48:1008::212:183:10]', $uri->getHost());

        $uri = new Uri('https://[2A00:F48:1008::212:183:10]');
        self::assertSame('[2a00:f48:1008::212:183:10]', $uri->getHost());

        $uri = new Uri('http://[2a00:f48:1008::212:183:10]:56?foo=bar');
        self::assertSame('[2a00:f48:1008::212:183:10]', $uri->getHost());
        self::assertSame(56, $uri->getPort());
        self::assertSame('foo=bar', $uri->getQuery());

        $uri = new Uri('https://[2a00:F48:1008::212:183:10]/path?foo=bar#frag');
        self::assertSame('[2a00:f48:1008::212:183:10]', $uri->getHost());
        self::assertSame('/path', $uri->getPath());
        self::assertSame('foo=bar', $uri->getQuery());
        self::assertSame('frag', $uri->getFragment());
    }

    public function test_json_serializable(): void
    {
        $uri = new Uri('https://example.com');

        self::assertSame('{"uri":"https:\/\/example.com"}', \json_encode(['uri' => $uri]));
    }
}

class Extended_Uri_Test extends Uri
{
}
