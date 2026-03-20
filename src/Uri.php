<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Guzzle_Http\Psr7\Exception\Malformed_Uri_Exception;
use Psr\Http\Message\Uri_Interface;
/**
 * PSR-7 URI implementation.
 *
 * @author Michael Dowling
 * @author Tobias Schultze
 * @author Matthew Weier O'Phinney
 */
class Uri implements Uri_Interface, \JsonSerializable
{
    /**
     * Absolute http and https URIs require a host per RFC 7230 Section 2.7
     * but in generic URIs the host can be empty. So for http(s) URIs
     * we apply this default host when no host is given yet to form a
     * valid URI.
     */
    private const HTTP_DEFAULT_HOST = 'localhost';
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443, 'ftp' => 21, 'gopher' => 70, 'nntp' => 119, 'news' => 119, 'telnet' => 23, 'tn3270' => 23, 'imap' => 143, 'pop' => 110, 'ldap' => 389];
    /**
     * Unreserved characters for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.3
     */
    private const CHAR_UNRESERVED = 'a-zA-Z0-9_\-\.~';
    /**
     * Sub-delims for use in a regex.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-2.2
     */
    private const CHAR_SUB_DELIMS = '!\$&\'\(\)\*\+,;=';
    private const QUERY_SEPARATORS_REPLACEMENT = ['=' => '%3D', '&' => '%26', '+' => '%2B'];
    /** @var string Uri scheme. */
    private $scheme = '';
    /** @var string Uri user info. */
    private $user_info = '';
    /** @var string Uri host. */
    private $host = '';
    /** @var int|null Uri port. */
    private $port;
    /** @var string Uri path. */
    private $path = '';
    /** @var string Uri query string. */
    private $query = '';
    /** @var string Uri fragment. */
    private $fragment = '';
    /** @var string|null String representation */
    private $composed_components;
    public function __construct(string $uri = '')
    {
        if ($uri !== '') {
            $parts = self::parse($uri);
            if ($parts === false) {
                throw new Malformed_Uri_Exception("Unable to parse URI: {$uri}");
            }
            $this->apply_parts($parts);
        }
    }
    /**
     * UTF-8 aware \parse_url() replacement.
     *
     * The internal function produces broken output for non ASCII domain names
     * (IDN) when used with locales other than "C".
     *
     * On the other hand, cURL understands IDN correctly only when UTF-8 locale
     * is configured ("C.UTF-8", "en_US.UTF-8", etc.).
     *
     * @see https://bugs.php.net/bug.php?id=52923
     * @see https://www.php.net/manual/en/function.parse-url.php#114817
     * @see https://curl.haxx.se/libcurl/c/CURLOPT_URL.html#ENCODING
     *
     * @return array|false
     */
    private static function parse(string $url)
    {
        // If IPv6
        $prefix = '';
        if (preg_match('%^(.*://\[[0-9:a-fA-F]+\])(.*?)$%', $url, $matches)) {
            /** @var array{0:string, 1:string, 2:string} $matches */
            $prefix = $matches[1];
            $url = $matches[2];
        }
        /** @var string */
        $encoded_url = preg_replace_callback('%[^:/@?&=#]+%usD', static function ($matches): string {
            return urlencode($matches[0]);
        }, $url);
        $result = parse_url($prefix . $encoded_url);
        if ($result === false) {
            return false;
        }
        return array_map('urldecode', $result);
    }
    public function __toString(): string
    {
        if ($this->composed_components === null) {
            $this->composed_components = self::compose_components($this->scheme, $this->get_authority(), $this->path, $this->query, $this->fragment);
        }
        return $this->composed_components;
    }
    /**
     * Composes a URI reference string from its various components.
     *
     * Usually this method does not need to be called manually but instead is used indirectly via
     * `Psr\Http\Message\UriInterface::__toString`.
     *
     * PSR-7 UriInterface treats an empty component the same as a missing component as
     * getQuery(), getFragment() etc. always return a string. This explains the slight
     * difference to RFC 3986 Section 5.3.
     *
     * Another adjustment is that the authority separator is added even when the authority is missing/empty
     * for the "file" scheme. This is because PHP stream functions like `file_get_contents` only work with
     * `file:///myfile` but not with `file:/myfile` although they are equivalent according to RFC 3986. But
     * `file:///` is the more common syntax for the file scheme anyway (Chrome for example redirects to
     * that format).
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.3
     */
    public static function compose_components(?string $scheme, ?string $authority, string $path, ?string $query, ?string $fragment): string
    {
        $uri = '';
        // weak type checks to also accept null until we can add scalar type hints
        if ($scheme != '') {
            $uri .= $scheme . ':';
        }
        if ($authority != '' || $scheme === 'file') {
            $uri .= '//' . $authority;
        }
        if ($authority != '' && $path != '' && $path[0] != '/') {
            $path = '/' . $path;
        }
        $uri .= $path;
        if ($query != '') {
            $uri .= '?' . $query;
        }
        if ($fragment != '') {
            $uri .= '#' . $fragment;
        }
        return $uri;
    }
    /**
     * Whether the URI has the default port of the current scheme.
     *
     * `Psr\Http\Message\UriInterface::getPort` may return null or the standard port. This method can be used
     * independently of the implementation.
     */
    public static function is_default_port(Uri_Interface $uri): bool
    {
        if ($uri->get_port() === null) {
            return true;
        }
        return isset(self::DEFAULT_PORTS[$uri->get_scheme()]) && $uri->get_port() === self::DEFAULT_PORTS[$uri->get_scheme()];
    }
    /**
     * Whether the URI is absolute, i.e. it has a scheme.
     *
     * An instance of UriInterface can either be an absolute URI or a relative reference. This method returns true
     * if it is the former. An absolute URI has a scheme. A relative reference is used to express a URI relative
     * to another URI, the base URI. Relative references can be divided into several forms:
     * - network-path references, e.g. '//example.com/path'
     * - absolute-path references, e.g. '/path'
     * - relative-path references, e.g. 'subpath'
     *
     * @see Uri::isNetworkPathReference
     * @see Uri::isAbsolutePathReference
     * @see Uri::isRelativePathReference
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4
     */
    public static function is_absolute(Uri_Interface $uri): bool
    {
        return $uri->get_scheme() !== '';
    }
    /**
     * Whether the URI is a network-path reference.
     *
     * A relative reference that begins with two slash characters is termed an network-path reference.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
     */
    public static function is_network_path_reference(Uri_Interface $uri): bool
    {
        return $uri->get_scheme() === '' && $uri->get_authority() !== '';
    }
    /**
     * Whether the URI is a absolute-path reference.
     *
     * A relative reference that begins with a single slash character is termed an absolute-path reference.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
     */
    public static function is_absolute_path_reference(Uri_Interface $uri): bool
    {
        return $uri->get_scheme() === '' && $uri->get_authority() === '' && isset($uri->get_path()[0]) && $uri->get_path()[0] === '/';
    }
    /**
     * Whether the URI is a relative-path reference.
     *
     * A relative reference that does not begin with a slash character is termed a relative-path reference.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
     */
    public static function is_relative_path_reference(Uri_Interface $uri): bool
    {
        return $uri->get_scheme() === '' && $uri->get_authority() === '' && (!isset($uri->get_path()[0]) || $uri->get_path()[0] !== '/');
    }
    /**
     * Whether the URI is a same-document reference.
     *
     * A same-document reference refers to a URI that is, aside from its fragment
     * component, identical to the base URI. When no base URI is given, only an empty
     * URI reference (apart from its fragment) is considered a same-document reference.
     *
     * @param UriInterface      $uri  The URI to check
     * @param UriInterface|null $base An optional base URI to compare against
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.4
     */
    public static function is_same_document_reference(Uri_Interface $uri, ?Uri_Interface $base = null): bool
    {
        if ($base !== null) {
            $uri = Uri_Resolver::resolve($base, $uri);
            return $uri->get_scheme() === $base->get_scheme() && $uri->get_authority() === $base->get_authority() && $uri->get_path() === $base->get_path() && $uri->get_query() === $base->get_query();
        }
        return $uri->get_scheme() === '' && $uri->get_authority() === '' && $uri->get_path() === '' && $uri->get_query() === '';
    }
    /**
     * Creates a new URI with a specific query string value removed.
     *
     * Any existing query string values that exactly match the provided key are
     * removed.
     *
     * @param UriInterface $uri URI to use as a base.
     * @param string       $key Query string key to remove.
     */
    public static function without_query_value(Uri_Interface $uri, string $key): Uri_Interface
    {
        $result = self::get_filtered_query_string($uri, [$key]);
        return $uri->with_query(implode('&', $result));
    }
    /**
     * Creates a new URI with a specific query string value.
     *
     * Any existing query string values that exactly match the provided key are
     * removed and replaced with the given key value pair.
     *
     * A value of null will set the query string key without a value, e.g. "key"
     * instead of "key=value".
     *
     * @param UriInterface $uri   URI to use as a base.
     * @param string       $key   Key to set.
     * @param string|null  $value Value to set
     */
    public static function with_query_value(Uri_Interface $uri, string $key, ?string $value): Uri_Interface
    {
        $result = self::get_filtered_query_string($uri, [$key]);
        $result[] = self::generate_query_string($key, $value);
        return $uri->with_query(implode('&', $result));
    }
    /**
     * Creates a new URI with multiple specific query string values.
     *
     * It has the same behavior as withQueryValue() but for an associative array of key => value.
     *
     * @param UriInterface    $uri           URI to use as a base.
     * @param (string|null)[] $keyValueArray Associative array of key and values
     */
    public static function with_query_values(Uri_Interface $uri, array $key_value_array): Uri_Interface
    {
        $result = self::get_filtered_query_string($uri, array_keys($key_value_array));
        foreach ($key_value_array as $key => $value) {
            $result[] = self::generate_query_string((string) $key, $value !== null ? (string) $value : null);
        }
        return $uri->with_query(implode('&', $result));
    }
    /**
     * Creates a URI from a hash of `parse_url` components.
     *
     * @see https://www.php.net/manual/en/function.parse-url.php
     *
     * @throws MalformedUriException If the components do not form a valid URI.
     */
    public static function from_parts(array $parts): Uri_Interface
    {
        $uri = new self();
        $uri->apply_parts($parts);
        $uri->validate_state();
        return $uri;
    }
    public function get_scheme(): string
    {
        return $this->scheme;
    }
    public function get_authority(): string
    {
        $authority = $this->host;
        if ($this->user_info !== '') {
            $authority = $this->user_info . '@' . $authority;
        }
        if ($this->port !== null) {
            $authority .= ':' . $this->port;
        }
        return $authority;
    }
    public function get_user_info(): string
    {
        return $this->user_info;
    }
    public function get_host(): string
    {
        return $this->host;
    }
    public function get_port(): ?int
    {
        return $this->port;
    }
    public function get_path(): string
    {
        return $this->path;
    }
    public function get_query(): string
    {
        return $this->query;
    }
    public function get_fragment(): string
    {
        return $this->fragment;
    }
    public function with_scheme($scheme): Uri_Interface
    {
        $scheme = $this->filter_scheme($scheme);
        if ($this->scheme === $scheme) {
            return $this;
        }
        $new = clone $this;
        $new->scheme = $scheme;
        $new->composed_components = null;
        $new->remove_default_port();
        $new->validate_state();
        return $new;
    }
    public function with_user_info($user, $password = null): Uri_Interface
    {
        $info = $this->filter_user_info_component($user);
        if ($password !== null) {
            $info .= ':' . $this->filter_user_info_component($password);
        }
        if ($this->user_info === $info) {
            return $this;
        }
        $new = clone $this;
        $new->user_info = $info;
        $new->composed_components = null;
        $new->validate_state();
        return $new;
    }
    public function with_host($host): Uri_Interface
    {
        $host = $this->filter_host($host);
        if ($this->host === $host) {
            return $this;
        }
        $new = clone $this;
        $new->host = $host;
        $new->composed_components = null;
        $new->validate_state();
        return $new;
    }
    public function with_port($port): Uri_Interface
    {
        $port = $this->filter_port($port);
        if ($this->port === $port) {
            return $this;
        }
        $new = clone $this;
        $new->port = $port;
        $new->composed_components = null;
        $new->remove_default_port();
        $new->validate_state();
        return $new;
    }
    public function with_path($path): Uri_Interface
    {
        $path = $this->filter_path($path);
        if ($this->path === $path) {
            return $this;
        }
        $new = clone $this;
        $new->path = $path;
        $new->composed_components = null;
        $new->validate_state();
        return $new;
    }
    public function with_query($query): Uri_Interface
    {
        $query = $this->filter_query_and_fragment($query);
        if ($this->query === $query) {
            return $this;
        }
        $new = clone $this;
        $new->query = $query;
        $new->composed_components = null;
        return $new;
    }
    public function with_fragment($fragment): Uri_Interface
    {
        $fragment = $this->filter_query_and_fragment($fragment);
        if ($this->fragment === $fragment) {
            return $this;
        }
        $new = clone $this;
        $new->fragment = $fragment;
        $new->composed_components = null;
        return $new;
    }
    public function jsonSerialize(): string
    {
        return $this->__toString();
    }
    /**
     * Apply parse_url parts to a URI.
     *
     * @param array $parts Array of parse_url parts to apply.
     */
    private function apply_parts(array $parts): void
    {
        $this->scheme = isset($parts['scheme']) ? $this->filter_scheme($parts['scheme']) : '';
        $this->user_info = isset($parts['user']) ? $this->filter_user_info_component($parts['user']) : '';
        $this->host = isset($parts['host']) ? $this->filter_host($parts['host']) : '';
        $this->port = isset($parts['port']) ? $this->filter_port($parts['port']) : null;
        $this->path = isset($parts['path']) ? $this->filter_path($parts['path']) : '';
        $this->query = isset($parts['query']) ? $this->filter_query_and_fragment($parts['query']) : '';
        $this->fragment = isset($parts['fragment']) ? $this->filter_query_and_fragment($parts['fragment']) : '';
        if (isset($parts['pass'])) {
            $this->user_info .= ':' . $this->filter_user_info_component($parts['pass']);
        }
        $this->remove_default_port();
    }
    /**
     * @param mixed $scheme
     *
     * @throws \InvalidArgumentException If the scheme is invalid.
     */
    private function filter_scheme($scheme): string
    {
        if (!is_string($scheme)) {
            throw new \InvalidArgumentException('Scheme must be a string');
        }
        return \strtr($scheme, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
    /**
     * @param mixed $component
     *
     * @throws \InvalidArgumentException If the user info is invalid.
     */
    private function filter_user_info_component($component): string
    {
        if (!is_string($component)) {
            throw new \InvalidArgumentException('User info must be a string');
        }
        return preg_replace_callback('/(?:[^%' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . ']+|%(?![A-Fa-f0-9]{2}))/', [$this, 'rawurlencodeMatchZero'], $component);
    }
    /**
     * @param mixed $host
     *
     * @throws \InvalidArgumentException If the host is invalid.
     */
    private function filter_host($host): string
    {
        if (!is_string($host)) {
            throw new \InvalidArgumentException('Host must be a string');
        }
        return \strtr($host, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }
    /**
     * @param mixed $port
     *
     * @throws \InvalidArgumentException If the port is invalid.
     */
    private function filter_port($port): ?int
    {
        if ($port === null) {
            return null;
        }
        $port = (int) $port;
        if (0 > $port || 0xffff < $port) {
            throw new \InvalidArgumentException(sprintf('Invalid port: %d. Must be between 0 and 65535', $port));
        }
        return $port;
    }
    /**
     * @param (string|int)[] $keys
     *
     * @return string[]
     */
    private static function get_filtered_query_string(Uri_Interface $uri, array $keys): array
    {
        $current = $uri->get_query();
        if ($current === '') {
            return [];
        }
        $decoded_keys = array_map(function ($k): string {
            return rawurldecode((string) $k);
        }, $keys);
        return array_filter(explode('&', $current), function ($part) use ($decoded_keys): bool {
            return !in_array(rawurldecode(explode('=', $part)[0]), $decoded_keys, true);
        });
    }
    private static function generate_query_string(string $key, ?string $value): string
    {
        // Query string separators ("=", "&") and literal plus signs ("+") within the
        // key or value need to be encoded
        // (while preventing double-encoding) before setting the query string. All other
        // chars that need percent-encoding will be encoded by withQuery().
        $query_string = strtr($key, self::QUERY_SEPARATORS_REPLACEMENT);
        if ($value !== null) {
            $query_string .= '=' . strtr($value, self::QUERY_SEPARATORS_REPLACEMENT);
        }
        return $query_string;
    }
    private function remove_default_port(): void
    {
        if ($this->port !== null && self::is_default_port($this)) {
            $this->port = null;
        }
    }
    /**
     * Filters the path of a URI
     *
     * @param mixed $path
     *
     * @throws \InvalidArgumentException If the path is invalid.
     */
    private function filter_path($path): string
    {
        if (!is_string($path)) {
            throw new \InvalidArgumentException('Path must be a string');
        }
        return preg_replace_callback('/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/', [$this, 'rawurlencodeMatchZero'], $path);
    }
    /**
     * Filters the query string or fragment of a URI.
     *
     * @param mixed $str
     *
     * @throws \InvalidArgumentException If the query or fragment is invalid.
     */
    private function filter_query_and_fragment($str): string
    {
        if (!is_string($str)) {
            throw new \InvalidArgumentException('Query and fragment must be a string');
        }
        return preg_replace_callback('/(?:[^' . self::CHAR_UNRESERVED . self::CHAR_SUB_DELIMS . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/', [$this, 'rawurlencodeMatchZero'], $str);
    }
    private function rawurlencode_match_zero(array $match): string
    {
        return rawurlencode($match[0]);
    }
    private function validate_state(): void
    {
        if ($this->host === '' && ($this->scheme === 'http' || $this->scheme === 'https')) {
            $this->host = self::HTTP_DEFAULT_HOST;
        }
        if ($this->get_authority() === '') {
            if (0 === strpos($this->path, '//')) {
                throw new Malformed_Uri_Exception('The path of a URI without an authority must not start with two slashes "//"');
            }
            if ($this->scheme === '' && false !== strpos(explode('/', $this->path, 2)[0], ':')) {
                throw new Malformed_Uri_Exception('A relative URI must not have a path beginning with a segment containing a colon');
            }
        }
    }
}