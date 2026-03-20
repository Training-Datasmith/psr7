<?php

declare (strict_types=1);
namespace Guzzle_Http\Psr7\Exception;

use InvalidArgumentException;
/**
 * Exception thrown if a URI cannot be parsed because it's malformed.
 */
class Malformed_Uri_Exception extends InvalidArgumentException
{
}