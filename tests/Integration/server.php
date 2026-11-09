<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

$request = Guzzle_Http\Psr7\Server_Request::from_globals();

$output = [
    'method' => $request->getMethod(),
    'uri' => $request->getUri()->__toString(),
    'body' => $request->getBody()->__toString(),
];

echo json_encode($output);
