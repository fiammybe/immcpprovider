<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth;

use ImMcpProvider\Module;
use Mcp\Exception\ClientRegistrationException;

final class ClientRegistrar
{
    private const MAX_CLIENTS = 500;

    private const MAX_REDIRECT_URIS = 10;

    private const MAX_NAME_LENGTH = 100;

    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    private const SUPPORTED_GRANT_TYPES = ['authorization_code', 'refresh_token'];

    /**
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     */
    public function register(array $request): array
    {
        $redirectUris = $this->validatedRedirectUris($request['redirect_uris'] ?? null);
        $name = $this->validatedName($request['client_name'] ?? null);

        $this->assertPublicClient($request);
        $this->assertSupportedGrantTypes($request['grant_types'] ?? null);

        $handler = Module::identifiedHandler('client');

        if ($handler->getCount() >= self::MAX_CLIENTS) {
            throw new ClientRegistrationException('Too many registered clients.', 'invalid_client_metadata');
        }

        $identifier = bin2hex(random_bytes(16));
        $issuedAt = time();
        $client = $handler->create();

        $client->setVar('identifier', $identifier);
        $client->setVar('name', $name);
        $client->setVar('redirect_uris', json_encode($redirectUris, JSON_UNESCAPED_SLASHES));
        $client->setVar('secret_hash', '');
        $client->setVar('created', $issuedAt);

        if (! $handler->insert($client, true)) {
            throw new ClientRegistrationException('Could not register the client.', 'server_error');
        }

        return [
            'client_id' => $identifier,
            'client_id_issued_at' => $issuedAt,
            'client_name' => $name,
            'redirect_uris' => $redirectUris,
            'grant_types' => self::SUPPORTED_GRANT_TYPES,
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ];
    }

    /** @return array<int, string> */
    private function validatedRedirectUris(mixed $redirectUris): array
    {
        if (! is_array($redirectUris) || $redirectUris === [] || ! array_is_list($redirectUris)) {
            throw new ClientRegistrationException('redirect_uris must be a non-empty array.', 'invalid_redirect_uri');
        }

        if (count($redirectUris) > self::MAX_REDIRECT_URIS) {
            throw new ClientRegistrationException('Too many redirect_uris.', 'invalid_redirect_uri');
        }

        foreach ($redirectUris as $uri) {
            if (! is_string($uri) || ! $this->isAcceptableRedirectUri($uri)) {
                throw new ClientRegistrationException(
                    'redirect_uris must be https URLs, or http URLs on a loopback host, without a fragment.',
                    'invalid_redirect_uri'
                );
            }
        }

        return array_values(array_unique($redirectUris));
    }

    private function isAcceptableRedirectUri(string $uri): bool
    {
        if (strlen($uri) > 2000) {
            return false;
        }

        $parts = parse_url($uri);

        if ($parts === false || isset($parts['fragment']) || isset($parts['user']) || ! isset($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');

        if ($scheme === 'https') {
            return true;
        }

        return $scheme === 'http' && in_array(strtolower($parts['host']), self::LOOPBACK_HOSTS, true);
    }

    private function validatedName(mixed $name): string
    {
        if (! is_string($name) || trim($name) === '') {
            return 'Unnamed MCP client';
        }

        return mb_substr(trim(strip_tags($name)), 0, self::MAX_NAME_LENGTH);
    }

    /** @param array<string, mixed> $request */
    private function assertPublicClient(array $request): void
    {
        $method = $request['token_endpoint_auth_method'] ?? 'none';

        if ($method !== 'none') {
            throw new ClientRegistrationException(
                'Only public clients (token_endpoint_auth_method "none") are supported.',
                'invalid_client_metadata'
            );
        }
    }

    private function assertSupportedGrantTypes(mixed $grantTypes): void
    {
        if ($grantTypes === null) {
            return;
        }

        if (! is_array($grantTypes) || array_diff($grantTypes, self::SUPPORTED_GRANT_TYPES) !== []) {
            throw new ClientRegistrationException(
                'Only the authorization_code and refresh_token grant types are supported.',
                'invalid_client_metadata'
            );
        }
    }
}
