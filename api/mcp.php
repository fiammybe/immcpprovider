<?php

declare(strict_types=1);

use ImMcpProvider\Http\EndpointRunner;
use ImMcpProvider\Http\ResponseEmitter;
use ImMcpProvider\ServerFactory;

require_once __DIR__ . '/../bootstrap.php';

$runner = new EndpointRunner(
    new ResponseEmitter(),
    ['jsonrpc' => '2.0', 'error' => ['code' => -32603, 'message' => 'Internal error.'], 'id' => null]
);

$runner->run(static function ($request) {
    $factory = new ServerFactory();

    return $factory->createServer()->run($factory->createTransport($request));
});
