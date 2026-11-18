<?php

declare(strict_types=1);

use ImMcpProvider\Http\EndpointRunner;
use ImMcpProvider\OAuth\OAuthMetadata;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadataHandler;

require_once __DIR__ . '/../bootstrap.php';

(new EndpointRunner())->run(static fn ($request, $factory) => (new ProtectedResourceMetadataHandler(
    OAuthMetadata::protectedResource(),
    $factory,
    $factory
))->handle($request)->withHeader('Cache-Control', 'public, max-age=3600'));
