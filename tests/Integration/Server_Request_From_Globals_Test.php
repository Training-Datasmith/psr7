<?php

declare(strict_types=1);

namespace Guzzle_Http\Tests\Psr7\Integration;

use PHPUnit\Framework\TestCase;

class Server_Request_From_Globals_Test extends TestCase
{
    protected function setUp(): void
    {
        if (false === $this->get_server_uri()) {
            self::markTestSkipped();
        }

        parent::setUp();
    }

    public function test_body_exists(): void
    {
        $curl = curl_init();

        curl_setopt($curl, CURLOPT_URL, $this->get_server_uri());
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, 'foobar');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($curl);

        if (PHP_VERSION_ID < 80000) {
            curl_close($curl);
        }

        self::assertNotFalse($response);
        $data = json_decode($response, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('method', $data);
        self::assertArrayHasKey('uri', $data);
        self::assertArrayHasKey('body', $data);

        self::assertEquals('foobar', $data['body']);
    }

    private function get_server_uri()
    {
        return $_SERVER['TEST_SERVER'] ?? false;
    }
}
