<?php

declare(strict_types=1);

namespace Guzzle_Http\Psr7;

use Psr\Http\Message\UriInterface;

/**
 * Resolves a URI reference in the context of a base URI and the opposite way.
 *
 * @author Tobias Schultze
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5
 */
final class Uri_Resolver
{
    /**
     * Removes dot segments from a path and returns the new path.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.2.4
     */
    public static function remove_dot_segments(string $path): string
    {
        if ($path === '' || $path === '/') {
            return $path;
        }

        $results = [];
        $segments = explode('/', $path);
        foreach ($segments as $segment) {
            if ($segment === '..') {
                array_pop($results);
            } elseif ($segment !== '.') {
                $results[] = $segment;
            }
        }

        $new_path = implode('/', $results);

        if ($path[0] === '/' && (!isset($new_path[0]) || $new_path[0] !== '/')) {
            // Re-add the leading slash if necessary for cases like "/.."
            $new_path = '/'.$new_path;
        } elseif ($new_path !== '' && ($segment === '.' || $segment === '..')) {
            // Add the trailing slash if necessary
            // If newPath is not empty, then $segment must be set and is the last segment from the foreach
            $new_path .= '/';
        }

        return $new_path;
    }

    /**
     * Converts the relative URI into a new URI that is resolved against the base URI.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.2
     */
    public static function resolve(UriInterface $base, UriInterface $rel): UriInterface
    {
        if ((string) $rel === '') {
            // we can simply return the same base URI instance for this same-document reference
            return $base;
        }

        if ($rel->getScheme() != '') {
            return $rel->withPath(self::remove_dot_segments($rel->getPath()));
        }

        if ($rel->getAuthority() != '') {
            $target_authority = $rel->getAuthority();
            $targetPath = self::remove_dot_segments($rel->getPath());
            $target_query = $rel->getQuery();
        } else {
            $target_authority = $base->getAuthority();
            if ($rel->getPath() === '') {
                $targetPath = $base->getPath();
                $target_query = $rel->getQuery() != '' ? $rel->getQuery() : $base->getQuery();
            } else {
                if ($rel->getPath()[0] === '/') {
                    $targetPath = $rel->getPath();
                } else {
                    if ($target_authority != '' && $base->getPath() === '') {
                        $targetPath = '/'.$rel->getPath();
                    } else {
                        $last_slash_pos = strrpos($base->getPath(), '/');
                        if ($last_slash_pos === false) {
                            $targetPath = $rel->getPath();
                        } else {
                            $targetPath = substr($base->getPath(), 0, $last_slash_pos + 1).$rel->getPath();
                        }
                    }
                }
                $targetPath = self::remove_dot_segments($targetPath);
                $target_query = $rel->getQuery();
            }
        }

        return new Uri(Uri::compose_components(
            $base->getScheme(),
            $target_authority,
            $targetPath,
            $target_query,
            $rel->getFragment()
        ));
    }

    /**
     * Returns the target URI as a relative reference from the base URI.
     *
     * This method is the counterpart to resolve():
     *
     *    (string) $target === (string) Uri_Resolver::resolve($base, Uri_Resolver::relativize($base, $target))
     *
     * One use-case is to use the current request URI as base URI and then generate relative links in your documents
     * to reduce the document size or offer self-contained downloadable document archives.
     *
     *    $base = new Uri('http://example.com/a/b/');
     *    echo Uri_Resolver::relativize($base, new Uri('http://example.com/a/b/c'));  // prints 'c'.
     *    echo Uri_Resolver::relativize($base, new Uri('http://example.com/a/x/y'));  // prints '../x/y'.
     *    echo Uri_Resolver::relativize($base, new Uri('http://example.com/a/b/?q')); // prints '?q'.
     *    echo Uri_Resolver::relativize($base, new Uri('http://example.org/a/b/'));   // prints '//example.org/a/b/'.
     *
     * This method also accepts a target that is already relative and will try to relativize it further. Only a
     * relative-path reference will be returned as-is.
     *
     *    echo Uri_Resolver::relativize($base, new Uri('/a/b/c'));  // prints 'c' as well
     */
    public static function relativize(UriInterface $base, UriInterface $target): UriInterface
    {
        if ($target->getScheme() !== ''
            && ($base->getScheme() !== $target->getScheme() || $target->getAuthority() === '' && $base->getAuthority() !== '')
        ) {
            return $target;
        }

        if (Uri::is_relative_path_reference($target)) {
            // As the target is already highly relative we return it as-is. It would be possible to resolve
            // the target with `$target = self::resolve($base, $target);` and then try make it more relative
            // by removing a duplicate query. But let's not do that automatically.
            return $target;
        }

        if ($target->getAuthority() !== '' && $base->getAuthority() !== $target->getAuthority()) {
            return $target->withScheme('');
        }

        // We must remove the path before removing the authority because if the path starts with two slashes, the URI
        // would turn invalid. And we also cannot set a relative path before removing the authority, as that is also
        // invalid.
        $empty_path_uri = $target->withScheme('')->withPath('')->withUserInfo('')->withPort(null)->withHost('');

        if ($base->getPath() !== $target->getPath()) {
            return $empty_path_uri->withPath(self::get_relative_path($base, $target));
        }

        if ($base->getQuery() === $target->getQuery()) {
            // Only the target fragment is left. And it must be returned even if base and target fragment are the same.
            return $empty_path_uri->withQuery('');
        }

        // If the base URI has a query but the target has none, we cannot return an empty path reference as it would
        // inherit the base query component when resolving.
        if ($target->getQuery() === '') {
            $segments = explode('/', $target->getPath());
            /** @var string $lastSegment */
            $last_segment = end($segments);

            return $empty_path_uri->withPath($last_segment === '' ? './' : $last_segment);
        }

        return $empty_path_uri;
    }

    private static function get_relative_path(UriInterface $base, UriInterface $target): string
    {
        $source_segments = explode('/', $base->getPath());
        $target_segments = explode('/', $target->getPath());
        array_pop($source_segments);
        $target_last_segment = array_pop($target_segments);
        foreach ($source_segments as $i => $segment) {
            if (isset($target_segments[$i]) && $segment === $target_segments[$i]) {
                unset($source_segments[$i], $target_segments[$i]);
            } else {
                break;
            }
        }
        $target_segments[] = $target_last_segment;
        $relative_path = str_repeat('../', count($source_segments)).implode('/', $target_segments);

        // A reference to am empty last segment or an empty first sub-segment must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name.
        if ('' === $relative_path || false !== strpos(explode('/', $relative_path, 2)[0], ':')) {
            $relative_path = "./$relative_path";
        } elseif ('/' === $relative_path[0]) {
            if ($base->getAuthority() != '' && $base->getPath() === '') {
                // In this case an extra slash is added by resolve() automatically. So we must not add one here.
                $relative_path = ".$relative_path";
            } else {
                $relative_path = "./$relative_path";
            }
        }

        return $relative_path;
    }

    private function __construct()
    {
        // cannot be instantiated
    }
}
