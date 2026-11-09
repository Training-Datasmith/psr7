<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7\Stream;
use Guzzle_Http\Psr7\Utils;

final class Read_Seek_Only_Stream extends Stream
{
    public function __construct()
    {
        parent::__construct(Utils::try_fopen('php://memory', 'wb'));
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function isReadable(): bool
    {
        return false;
    }
}
