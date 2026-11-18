<?php

declare(strict_types=1);

use ImMcpProvider\Http\EndpointRunner;
use ImMcpProvider\OAuth\OAuthMetadata;

require_once __DIR__ . '/../bootstrap.php';

(new EndpointRunner())->run(static fn ($request, $factory) => $factory
    ->createResponse(200)
    ->withHeader('Content-Type', 'application/json')
    ->withHeader('Cache-Control', 'public, max-age=3600')
    ->withBody($factory->createStream((string) json_encode(OAuthMetadata::authorizationServer(), JSON_UNESCAPED_SLASHES))));
