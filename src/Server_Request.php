<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\Server_Request_Interface;
use Psr\Http\Message\Stream_Interface;
use Psr\Http\Message\Uploaded_File_Interface;
use Psr\Http\Message\Uri_Interface;
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
class Server_Request extends Request implements Server_Request_Interface
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
     * @param array                                $serverParams Typically the $_SERVER superglobal
     */
    public function __construct(string $method, $uri, array $headers = [], $body = null, string $version = '1.1', array $server_params = [])
    {
        $this->server_params = $server_params;
        parent::__construct($method, $uri, $headers, $body, $version);
    }
    /**
     * Return an UploadedFile instance array.
     *
     * @param array $files An array which respect $_FILES structure
     *
     * @throws InvalidArgumentException for unrecognized values
     */
    public static function normalize_files(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $value) {
            if ($value instanceof Uploaded_File_Interface) {
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
     * Create and return an UploadedFile instance from a $_FILES specification.
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
        return new Uploaded_File($value['tmp_name'], (int) $value['size'], (int) $value['error'], $value['name'], $value['type']);
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
            $spec = ['tmp_name' => $files['tmp_name'][$key], 'size' => $files['size'][$key] ?? null, 'error' => $files['error'][$key] ?? null, 'name' => $files['name'][$key] ?? null, 'type' => $files['type'][$key] ?? null];
            $normalized_files[$key] = self::create_uploaded_file_from_spec($spec);
        }
        return $normalized_files;
    }
    /**
     * Return a ServerRequest populated with superglobals:
     * $_GET
     * $_POST
     * $_COOKIE
     * $_FILES
     * $_SERVER
     */
    public static function from_globals(): Server_Request_Interface
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $headers = getallheaders();
        $uri = self::get_uri_from_globals();
        $body = new Caching_Stream(new Lazy_Open_Stream('php://input', 'r+'));
        $protocol = isset($_SERVER['SERVER_PROTOCOL']) ? str_replace('HTTP/', '', $_SERVER['SERVER_PROTOCOL']) : '1.1';
        $server_request = new Server_Request($method, $uri, $headers, $body, $protocol, $_SERVER);
        return $server_request->with_cookie_params($_COOKIE)->with_query_params($_GET)->with_parsed_body($_POST)->with_uploaded_files(self::normalize_files($_FILES));
    }
    private static function extract_host_and_port_from_authority(string $authority): array
    {
        $uri = 'http://' . $authority;
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
    public static function get_uri_from_globals(): Uri_Interface
    {
        $uri = new Uri('');
        $uri = $uri->with_scheme(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http');
        $has_port = false;
        if (isset($_SERVER['HTTP_HOST'])) {
            [$host, $port] = self::extract_host_and_port_from_authority($_SERVER['HTTP_HOST']);
            if ($host !== null) {
                $uri = $uri->with_host($host);
            }
            if ($port !== null) {
                $has_port = true;
                $uri = $uri->with_port($port);
            }
        } elseif (isset($_SERVER['SERVER_NAME'])) {
            $uri = $uri->with_host($_SERVER['SERVER_NAME']);
        } elseif (isset($_SERVER['SERVER_ADDR'])) {
            $uri = $uri->with_host($_SERVER['SERVER_ADDR']);
        }
        if (!$has_port && isset($_SERVER['SERVER_PORT'])) {
            $uri = $uri->with_port($_SERVER['SERVER_PORT']);
        }
        $has_query = false;
        if (isset($_SERVER['REQUEST_URI'])) {
            $request_uri_parts = explode('?', $_SERVER['REQUEST_URI'], 2);
            $uri = $uri->with_path($request_uri_parts[0]);
            if (isset($request_uri_parts[1])) {
                $has_query = true;
                $uri = $uri->with_query($request_uri_parts[1]);
            }
        }
        if (!$has_query && isset($_SERVER['QUERY_STRING'])) {
            return $uri->with_query($_SERVER['QUERY_STRING']);
        }
        return $uri;
    }
    public function get_server_params(): array
    {
        return $this->server_params;
    }
    public function get_uploaded_files(): array
    {
        return $this->uploaded_files;
    }
    public function with_uploaded_files(array $uploaded_files): Server_Request_Interface
    {
        $new = clone $this;
        $new->uploaded_files = $uploaded_files;
        return $new;
    }
    public function get_cookie_params(): array
    {
        return $this->cookie_params;
    }
    public function with_cookie_params(array $cookies): Server_Request_Interface
    {
        $new = clone $this;
        $new->cookie_params = $cookies;
        return $new;
    }
    public function get_query_params(): array
    {
        return $this->query_params;
    }
    public function with_query_params(array $query): Server_Request_Interface
    {
        $new = clone $this;
        $new->query_params = $query;
        return $new;
    }
    /**
     * @return array|object|null
     */
    public function get_parsed_body()
    {
        return $this->parsed_body;
    }
    public function with_parsed_body($data): Server_Request_Interface
    {
        $new = clone $this;
        $new->parsed_body = $data;
        return $new;
    }
    public function get_attributes(): array
    {
        return $this->attributes;
    }
    /**
     * @return mixed
     */
    public function get_attribute($attribute, $default = null)
    {
        if (false === array_key_exists($attribute, $this->attributes)) {
            return $default;
        }
        return $this->attributes[$attribute];
    }
    public function with_attribute($attribute, $value): Server_Request_Interface
    {
        $new = clone $this;
        $new->attributes[$attribute] = $value;
        return $new;
    }
    public function without_attribute($attribute): Server_Request_Interface
    {
        if (false === array_key_exists($attribute, $this->attributes)) {
            return $this;
        }
        $new = clone $this;
        unset($new->attributes[$attribute]);
        return $new;
    }
}