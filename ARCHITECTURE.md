# Architecture: psr7

## Purpose

A PHP implementation of PSR-7 (HTTP Message Interface) and PSR-17 (HTTP Factory Interface). Provides immutable value objects for HTTP requests, responses, URIs, streams, and uploaded files — used by frameworks and HTTP clients (primarily Guzzle).

## Directory Structure

```
src/
  Request.php                 - PSR-7 RequestInterface implementation
  Response.php                - PSR-7 ResponseInterface implementation
  Server_Request.php          - PSR-7 ServerRequestInterface implementation
  Uri.php                     - PSR-7 UriInterface implementation
  Stream.php                  - PSR-7 StreamInterface wrapping PHP streams
  Uploaded_File.php           - PSR-7 UploadedFileInterface implementation
  Http_Factory.php            - PSR-17 factory for creating all PSR-7 objects
  Message_Trait.php           - Shared logic for Request/Response (headers, body)
  Utils.php                   - Static helpers: streamFor(), copyToStream(), etc.
  Header.php                  - Header value parsing utilities
  Query.php                   - URL query string encoding/decoding utilities
  Uri_Normaliser.php          - RFC 3986 URI normalisation
  Uri_Comparator.php          - URI equality comparison (origin/path matching)
  Uri_Resolver.php            - Resolves relative URIs against a base URI
  Mime_Type.php               - MIME type detection by file extension
  Stream decorators:
    Append_Stream.php         - Concatenates multiple streams
    Buffer_Stream.php         - Buffered in-memory stream
    Caching_Stream.php        - Caches a stream as it is read
    Dropping_Stream.php       - Drops writes when buffer is full
    Inflate_Stream.php        - Decompresses gzip/deflate streams on-the-fly
    Lazy_Open_Stream.php      - Opens a file path lazily on first read
    Limit_Stream.php          - Limits reads to a subset of another stream
    No_Seek_Stream.php        - Prevents seeking on a stream
    Pump_Stream.php           - Creates a stream from a callable pump function
    Fn_Stream.php             - Stream backed by user-provided callables
  Exception/
    Malformed_Uri_Exception.php - Thrown when a URI cannot be parsed
```

## Key Design Decisions

- **Immutable value objects**: All PSR-7 objects are immutable; mutating methods return new instances (`withHeader()`, `withBody()`, etc.).
- **Stream decorator pattern**: Stream decorators compose functionality without subclassing, keeping each decorator focused on a single concern.
- **RFC compliance**: `Uri` implements full RFC 3986 parsing; `Rfc7230` validates HTTP header field names and values.
- **PSR-17 factory included**: `Http_Factory` implements all PSR-17 factory interfaces, providing a single object for dependency injection.

## Extension Points

- Wrap any `StreamInterface` with a decorator to add buffering, compression, or rate-limiting.
- Extend `Uri` for domain-specific URI schemes.

## Dependency Flow

```
Http_Factory (PSR-17)
  └─> creates Request, Response, ServerRequest, Uri, Stream, UploadedFile
Request / Response
  └─> Message_Trait — shared header/body handling
  └─> Uri           — parsed URI
  └─> Stream        — body stream
```
