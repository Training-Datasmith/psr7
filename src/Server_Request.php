<?php

declare(strict_types=1);

namespace Guzzle_Http\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * Server-side HTTP request
 *
 * Extends the Request definition to add methods for accessing incoming data,
 * specifically server parameters, cookies, matched path parameters, query
 * string arguments, body parameters, and upload file information.
 *
 * "Attributes" are discovered via decomposing the request (and usually
 * specifically the URI path), and typically will be injected by the application.
 *
 * Requests are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class Server_Request extends Request implements ServerRequestInterface
{
    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $cookie_params = [];

    /**
     * @var array|object|null
     */
    private $parsed_body;

    /**
     * @var array
     */
    private $query_params = [];

    /**
     * @var array
     */
    private $server_params;

    /**
     * @var array
     */
    private $uploaded_files = [];

    /**
     * @param string                               $method       HTTP method
     * @param string|UriInterface                  $uri          URI
     * @param (string|string[])[]                  $headers      Request headers
     * @param string|resource|StreamInterface|null $body         Request body
     * @param string                               $version      Protocol version
     * @param array                                $server_params Typically the $_SERVER superglobal
     */
    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        $body = null,
        string $version = '1.1',
        array $server_params = []
    ) {
        $this->server_params = $server_params;

        parent::__construct($method, $uri, $headers, $body, $version);
    }

    /**
     * Return an Uploaded_File instance array.
     *
     * @param array $files An array which respect $_FILES structure
     *
     * @throws InvalidArgumentException for unrecognized values
     */
    public static function normalize_files(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (is_array($value) && isset($value['tmp_name'])) {
                $normalized[$key] = self::create_uploaded_file_from_spec($value);
            } elseif (is_array($value)) {
                $normalized[$key] = self::normalize_files($value);
                continue;
            } else {
                throw new InvalidArgumentException('Invalid value in files specification');
            }
        }

        return $normalized;
    }

    /**
     * Create and return an Uploaded_File instance from a $_FILES specification.
     *
     * If the specification represents an array of values, this method will
     * delegate to normalizeNestedFileSpec() and return that return value.
     *
     * @param array $value $_FILES struct
     *
     * @return UploadedFileInterface|UploadedFileInterface[]
     */
    private static function create_uploaded_file_from_spec(array $value)
    {
        if (is_array($value['tmp_name'])) {
            return self::normalize_nested_file_spec($value);
        }

        return new Uploaded_File(
            $value['tmp_name'],
            (int) $value['size'],
            (int) $value['error'],
            $value['name'],
            $value['type']
        );
    }

    /**
     * Normalize an array of file specifications.
     *
     * Loops through all nested files and returns a normalized array of
     * UploadedFileInterface instances.
     *
     * @return UploadedFileInterface[]
     */
    private static function normalize_nested_file_spec(array $files = []): array
    {
        $normalized_files = [];

        foreach (array_keys($files['tmp_name']) as $key) {
            $spec = [
                'tmp_name' => $files['tmp_name'][$key],
                'size' => $files['size'][$key] ?? null,
                'error' => $files['error'][$key] ?? null,
                'name' => $files['name'][$key] ?? null,
                'type' => $files['type'][$key] ?? null,
            ];
            $normalized_files[$key] = self::create_uploaded_file_from_spec($spec);
        }

        return $normalized_files;
    }

    /**
     * Return a Server_Request populated with superglobals:
     * $_GET
     * $_POST
     * $_COOKIE
     * $_FILES
     * $_SERVER
     */
    public static function from_globals(): ServerRequestInterface
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $headers = getallheaders();
        $uri = self::get_uri_from_globals();
        $body = new Caching_Stream(new Lazy_Open_Stream('php://input', 'r+'));
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL']) : '1.1';

        $server_request = new Server_Request($method, $uri, $headers, $body, $protocol, $_SERVER);

        return $server_request
            ->withCookieParams($_COOKIE)
            ->withQueryParams($_GET)
            ->withParsedBody($_POST)
            ->withUploadedFiles(self::normalize_files($_FILES));
    }

    private static function extract_host_and_port_from_authority(string $authority): array
    {
        $uri = 'http://'.$authority;
        $parts = parse_url($uri);
        if (false === $parts) {
            return [null, null];
        }

        $host = $parts['host'] ?? null;
        $port = $parts['port'] ?? null;

        return [$host, $port];
    }

    /**
     * Get a Uri populated with values from $_SERVER.
     */
    public static function get_uri_from_globals(): UriInterface
    {
        $uri = new Uri('');

        $uri = $uri->withScheme(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');

        $has_port = false;
        if (isset($_SERVER['HTTP_HOST'])) {
            [$host, $port] = self::extract_host_and_port_from_authority($_SERVER['HTTP_HOST']);
            if ($host !== null) {
                $uri = $uri->withHost($host);
            }

            if ($port !== null) {
                $has_port = true;
                $uri = $uri->withPort($port);
            }
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $uri = $uri->withHost($_SERVER['SERVER_NAME']);
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $uri = $uri->withHost($_SERVER['SERVER_ADDR']);
        }

        if (!$has_port && isset($_SERVER['SERVER_PORT'])) {
            $uri = $uri->withPort($_SERVER['SERVER_PORT']);
        }

        $has_query = false;
        if (isset($_SERVER['REQUEST_URI'])) {
            $request_uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
            $uri = $uri->withPath($request_uri_parts[0]);
            if (isset($request_uri_parts[1])) {
                $has_query = true;
                $uri = $uri->withQuery($request_uri_parts[1]);
            }
        }

        if (!$has_query && isset($_SERVER['QUERY_STRING'])) {
            $uri = $uri->withQuery($_SERVER['QUERY_STRING']);
        }

        return $uri;
    }

    public function getServerParams(): array
    {
        return $this->server_params;
    }

    public function getUploadedFiles(): array
    {
        return $this->uploaded_files;
    }

    public function withUploadedFiles(array $uploaded_files): ServerRequestInterface
    {
        $new = clone $this;
        $new->uploaded_files = $uploaded_files;

        return $new;
    }

    public function getCookieParams(): array
    {
        return $this->cookie_params;
    }

    public function withCookieParams(array $cookies): ServerRequestInterface
    {
        $new = clone $this;
        $new->cookie_params = $cookies;

        return $new;
    }

    public function getQueryParams(): array
    {
        return $this->query_params;
    }

    public function withQueryParams(array $query): ServerRequestInterface
    {
        $new = clone $this;
        $new->query_params = $query;

        return $new;
    }

    /**
     * @return array|object|null
     */
    public function getParsedBody()
    {
        return $this->parsed_body;
    }

    public function withParsedBody($data): ServerRequestInterface
    {
        $new = clone $this;
        $new->parsed_body = $data;

        return $new;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * @return mixed
     */
    public function getAttribute($attribute, $default = null)
    {
        if (false === array_key_exists($attribute, $this->attributes)) {
            return $default;
        }

        return $this->attributes[$attribute];
    }

    public function withAttribute($attribute, $value): ServerRequestInterface
    {
        $new = clone $this;
        $new->attributes[$attribute] = $value;

        return $new;
    }

    public function withoutAttribute($attribute): ServerRequestInterface
    {
        if (false === array_key_exists($attribute, $this->attributes)) {
            return $this;
        }

        $new = clone $this;
        unset($new->attributes[$attribute]);

        return $new;
    }
}
