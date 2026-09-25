<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth;

use ImMcpProvider\Module;
use Mcp\Server\Transport\Http\OAuth\ProtectedResourceMetadata;

final class OAuthMetadata
{
    public const RESOURCE_NAME = 'ImpressCMS MCP Provider';

    /** @return array<string, mixed> */
    public static function authorizationServer(): array
    {
        return [
            'issuer' => Module::issuer(),
            'authorization_endpoint' => Module::url('oauth/authorize.php'),
            'token_endpoint' => Module::url('oauth/token.php'),
            'registration_endpoint' => Module::url('oauth/register.php'),
            'jwks_uri' => Module::url('oauth/jwks.php'),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported' => [Scope::MCP],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
        ];
    }

    public static function protectedResource(): ProtectedResourceMetadata
    {
        return new ProtectedResourceMetadata(
            authorizationServers: [Module::issuer()],
            scopesSupported: [Scope::MCP],
            resource: Module::url('api/mcp.php'),
            resourceName: self::RESOURCE_NAME,
            metadataPaths: [(string) parse_url(Module::url('oauth/protected-resource.php'), PHP_URL_PATH)],
        );
    }
}
