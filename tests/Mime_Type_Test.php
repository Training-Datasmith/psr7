<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7;

use Guzzle_Http\Psr7;
use PHPUnit\Framework\TestCase;

class Mime_Type_Test extends TestCase
{
    public function test_determine_from_extension(): void
    {
        self::assertNull(Psr7\Mime_Type::from_extension('not-a-real-extension'));
        self::assertSame('application/json', Psr7\Mime_Type::from_extension('json'));
    }

    public function test_determine_from_filename(): void
    {
        self::assertSame(
            'image/jpeg',
            Psr7\Mime_Type::from_filename('/tmp/images/IMG034821.JPEG')
        );
    }
}
