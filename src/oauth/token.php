<?php

declare(strict_types=1);

use ImMcpProvider\Http\EndpointRunner;
use ImMcpProvider\OAuth\OAuthServices;

require_once __DIR__ . '/../bootstrap.php';

(new EndpointRunner())->run(static function ($request, $factory) {
    $response = $factory->createResponse();

    return OAuthServices::create()
        ->authorizationServer()
        ->respondToAccessTokenRequest($request, $response)
        ->withHeader('Cache-Control', 'no-store');
});
