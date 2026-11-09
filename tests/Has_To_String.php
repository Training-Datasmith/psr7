<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

class Has_To_String
{
    public function __toString(): string
    {
        return 'foo';
    }
}
