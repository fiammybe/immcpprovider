<?php

declare(strict_types=1);

use ImMcpProvider\Http\EndpointRunner;
use ImMcpProvider\OAuth\ClientRegistrar;
use Mcp\Exception\ClientRegistrationException;

require_once __DIR__ . '/../bootstrap.php';

(new EndpointRunner())->run(static function ($request, $factory) {
    $respond = static fn (int $status, array $body) => $factory
        ->createResponse($status)
        ->withHeader('Content-Type', 'application/json')
        ->withHeader('Cache-Control', 'no-store')
        ->withBody($factory->createStream((string) json_encode($body, JSON_UNESCAPED_SLASHES)));

    if ($request->getMethod() !== 'POST') {
        return $respond(405, ['error' => 'invalid_request'])->withHeader('Allow', 'POST');
    }

    $payload = json_decode((string) $request->getBody(), true);

    if (! is_array($payload) || array_is_list($payload)) {
        return $respond(400, ['error' => 'invalid_client_metadata', 'error_description' => 'The request body must be a JSON object.']);
    }

    try {
        return $respond(201, (new ClientRegistrar())->register($payload));
    } catch (ClientRegistrationException $exception) {
        return $respond(400, ['error' => $exception->errorCode, 'error_description' => $exception->getMessage()]);
    }
});
