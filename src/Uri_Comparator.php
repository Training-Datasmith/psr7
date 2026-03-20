<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7;

use Psr\Http\Message\Uri_Interface;
/**
 * Provides methods to determine if a modified URL should be considered cross-origin.
 *
 * @author Graham Campbell
 */
final class Uri_Comparator
{
    /**
     * Determines if a modified URL should be considered cross-origin with
     * respect to an original URL.
     */
    public static function is_cross_origin(Uri_Interface $original, Uri_Interface $modified): bool
    {
        if (\strcasecmp($original->get_host(), $modified->get_host()) !== 0) {
            return true;
        }
        if ($original->get_scheme() !== $modified->get_scheme()) {
            return true;
        }
        if (self::compute_port($original) !== self::compute_port($modified)) {
            return true;
        }
        return false;
    }
    private static function compute_port(Uri_Interface $uri): int
    {
        $port = $uri->get_port();
        if (null !== $port) {
            return $port;
        }
        return 'https' === $uri->get_scheme() ? 443 : 80;
    }
    private function __construct()
    {
        // cannot be instantiated
    }
}