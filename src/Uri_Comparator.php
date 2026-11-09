<?php

declare(strict_types=1);

namespace Guzzle_Http\Psr7;

use Psr\Http\Message\UriInterface;

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
    public static function is_cross_origin(UriInterface $original, UriInterface $modified): bool
    {
        if (\strcasecmp($original->getHost(), $modified->getHost()) !== 0) {
            return true;
        }

        if ($original->getScheme() !== $modified->getScheme()) {
            return true;
        }

        if (self::compute_port($original) !== self::compute_port($modified)) {
            return true;
        }

        return false;
    }

    private static function compute_port(UriInterface $uri): int
    {
        $port = $uri->getPort();

        if (null !== $port) {
            return $port;
        }

        return 'https' === $uri->getScheme() ? 443 : 80;
    }

    private function __construct()
    {
        // cannot be instantiated
    }
}
