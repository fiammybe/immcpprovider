<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth;

use DateInterval;
use ImMcpProvider\OAuth\Repository\AccessTokenRepository;
use ImMcpProvider\OAuth\Repository\AuthCodeRepository;
use ImMcpProvider\OAuth\Repository\ClientRepository;
use ImMcpProvider\OAuth\Repository\RefreshTokenRepository;
use ImMcpProvider\OAuth\Repository\ScopeRepository;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\CryptKey;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;

final class OAuthServices
{
    private const AUTH_CODE_TTL = 'PT10M';

    private const ACCESS_TOKEN_TTL = 'PT1H';

    private const REFRESH_TOKEN_TTL = 'P30D';

    public function __construct(private readonly KeyStore $keys)
    {
    }

    public static function create(): self
    {
        return new self(KeyStore::fromTrustPath());
    }

    public function authorizationServer(): AuthorizationServer
    {
        $accessTokens = new AccessTokenRepository();
        $refreshTokens = new RefreshTokenRepository();

        $server = new AuthorizationServer(
            new ClientRepository(),
            $accessTokens,
            new ScopeRepository(),
            new CryptKey($this->keys->privateKey(), null, false),
            $this->keys->encryptionKey(),
            new ScopedBearerTokenResponse()
        );

        $authCodeGrant = new AuthCodeGrant(
            new AuthCodeRepository(),
            $refreshTokens,
            new DateInterval(self::AUTH_CODE_TTL)
        );
        $authCodeGrant->setRefreshTokenTTL(new DateInterval(self::REFRESH_TOKEN_TTL));

        $refreshGrant = new RefreshTokenGrant($refreshTokens);
        $refreshGrant->setRefreshTokenTTL(new DateInterval(self::REFRESH_TOKEN_TTL));

        $server->enableGrantType($authCodeGrant, new DateInterval(self::ACCESS_TOKEN_TTL));
        $server->enableGrantType($refreshGrant, new DateInterval(self::ACCESS_TOKEN_TTL));
        $server->setDefaultScope(Scope::MCP);

        return $server;
    }

    public function resourceServer(): ResourceServer
    {
        return new ResourceServer(
            new AccessTokenRepository(),
            new CryptKey($this->keys->publicKey(), null, false)
        );
    }
}
