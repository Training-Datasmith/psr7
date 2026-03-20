<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Uri_Interface;
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
            $new_path = '/' . $new_path;
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
    public static function resolve(Uri_Interface $base, Uri_Interface $rel): Uri_Interface
    {
        if ((string) $rel === '') {
            // we can simply return the same base URI instance for this same-document reference
            return $base;
        }
        if ($rel->get_scheme() != '') {
            return $rel->with_path(self::remove_dot_segments($rel->get_path()));
        }
        if ($rel->get_authority() != '') {
            $target_authority = $rel->get_authority();
            $target_path = self::remove_dot_segments($rel->get_path());
            $target_query = $rel->get_query();
        } else {
            $target_authority = $base->get_authority();
            if ($rel->get_path() === '') {
                $target_path = $base->get_path();
                $target_query = $rel->get_query() != '' ? $rel->get_query() : $base->get_query();
            } else {
                if ($rel->get_path()[0] === '/') {
                    $target_path = $rel->get_path();
                } else if ($target_authority != '' && $base->get_path() === '') {
                    $target_path = '/' . $rel->get_path();
                } else {
                    $last_slash_pos = strrpos($base->get_path(), '/');
                    if ($last_slash_pos === false) {
                        $target_path = $rel->get_path();
                    } else {
                        $target_path = substr($base->get_path(), 0, $last_slash_pos + 1) . $rel->get_path();
                    }
                }
                $target_path = self::remove_dot_segments($target_path);
                $target_query = $rel->get_query();
            }
        }
        return new Uri(Uri::compose_components($base->get_scheme(), $target_authority, $target_path, $target_query, $rel->get_fragment()));
    }
    /**
     * Returns the target URI as a relative reference from the base URI.
     *
     * This method is the counterpart to resolve():
     *
     *    (string) $target === (string) UriResolver::resolve($base, UriResolver::relativize($base, $target))
     *
     * One use-case is to use the current request URI as base URI and then generate relative links in your documents
     * to reduce the document size or offer self-contained downloadable document archives.
     *
     *    $base = new Uri('http://example.com/a/b/');
     *    echo UriResolver::relativize($base, new Uri('http://example.com/a/b/c'));  // prints 'c'.
     *    echo UriResolver::relativize($base, new Uri('http://example.com/a/x/y'));  // prints '../x/y'.
     *    echo UriResolver::relativize($base, new Uri('http://example.com/a/b/?q')); // prints '?q'.
     *    echo UriResolver::relativize($base, new Uri('http://example.org/a/b/'));   // prints '//example.org/a/b/'.
     *
     * This method also accepts a target that is already relative and will try to relativize it further. Only a
     * relative-path reference will be returned as-is.
     *
     *    echo UriResolver::relativize($base, new Uri('/a/b/c'));  // prints 'c' as well
     */
    public static function relativize(Uri_Interface $base, Uri_Interface $target): Uri_Interface
    {
        if ($target->get_scheme() !== '' && ($base->get_scheme() !== $target->get_scheme() || $target->get_authority() === '' && $base->get_authority() !== '')) {
            return $target;
        }
        if (Uri::is_relative_path_reference($target)) {
            // As the target is already highly relative we return it as-is. It would be possible to resolve
            // the target with `$target = self::resolve($base, $target);` and then try make it more relative
            // by removing a duplicate query. But let's not do that automatically.
            return $target;
        }
        if ($target->get_authority() !== '' && $base->get_authority() !== $target->get_authority()) {
            return $target->with_scheme('');
        }
        // We must remove the path before removing the authority because if the path starts with two slashes, the URI
        // would turn invalid. And we also cannot set a relative path before removing the authority, as that is also
        // invalid.
        $empty_path_uri = $target->with_scheme('')->with_path('')->with_user_info('')->with_port(null)->with_host('');
        if ($base->get_path() !== $target->get_path()) {
            return $empty_path_uri->with_path(self::get_relative_path($base, $target));
        }
        if ($base->get_query() === $target->get_query()) {
            // Only the target fragment is left. And it must be returned even if base and target fragment are the same.
            return $empty_path_uri->with_query('');
        }
        // If the base URI has a query but the target has none, we cannot return an empty path reference as it would
        // inherit the base query component when resolving.
        if ($target->get_query() === '') {
            $segments = explode('/', $target->get_path());
            /** @var string $lastSegment */
            $last_segment = end($segments);
            return $empty_path_uri->with_path($last_segment === '' ? './' : $last_segment);
        }
        return $empty_path_uri;
    }
    private static function get_relative_path(Uri_Interface $base, Uri_Interface $target): string
    {
        $source_segments = explode('/', $base->get_path());
        $target_segments = explode('/', $target->get_path());
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
        $relative_path = str_repeat('../', count($source_segments)) . implode('/', $target_segments);
        // A reference to am empty last segment or an empty first sub-segment must be prefixed with "./".
        // This also applies to a segment with a colon character (e.g., "file:colon") that cannot be used
        // as the first segment of a relative-path reference, as it would be mistaken for a scheme name.
        if (false !== strpos(explode('/', $relative_path, 2)[0], ':')) {
            $relative_path = "./{$relative_path}";
        } elseif ('/' === $relative_path[0]) {
            if ($base->get_authority() != '' && $base->get_path() === '') {
                // In this case an extra slash is added by resolve() automatically. So we must not add one here.
                $relative_path = ".{$relative_path}";
            } else {
                $relative_path = "./{$relative_path}";
            }
        }
        return $relative_path;
    }
    private function __construct()
    {
        // cannot be instantiated
    }
}