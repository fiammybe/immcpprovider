<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth;

use ImMcpProvider\OAuth\Entity\AccessTokenEntity;
use ImMcpProvider\Support\ActivityLog;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Mcp\Server\Transport\Http\OAuth\AuthorizationResult;
use Mcp\Server\Transport\Http\OAuth\AuthorizationTokenValidatorInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;

final class BearerTokenValidator implements AuthorizationTokenValidatorInterface
{
    public const USER_ATTRIBUTE = 'oauth_user_id';

    public function __construct(
        private readonly ResourceServer $resourceServer,
        private readonly ServerRequestFactoryInterface $requestFactory
    ) {
    }

    public function validate(string $accessToken): AuthorizationResult
    {
        $request = $this->requestFactory
            ->createServerRequest('POST', '/')
            ->withHeader('Authorization', "Bearer {$accessToken}");

        try {
            $validated = $this->resourceServer->validateAuthenticatedRequest($request);
        } catch (OAuthServerException $exception) {
            ActivityLog::record("token rejected: {$exception->getErrorType()} hint=\"{$exception->getHint()}\" message=\"{$exception->getMessage()}\"");

            return AuthorizationResult::unauthorized('invalid_token', 'The access token is invalid, expired or revoked.');
        }

        if (! $this->isIssuedForThisResource($accessToken)) {
            ActivityLog::record('token rejected: the audience does not include this MCP endpoint');

            return AuthorizationResult::unauthorized('invalid_token', 'The access token was not issued for this resource.');
        }

        $scopes = $validated->getAttribute('oauth_scopes', []);

        if (! in_array(Scope::MCP, $scopes, true)) {
            ActivityLog::record('token rejected: missing the mcp scope');

            return AuthorizationResult::unauthorized('insufficient_scope', 'The access token lacks the mcp scope.', [Scope::MCP]);
        }

        return AuthorizationResult::allow([
            self::USER_ATTRIBUTE => (string) $validated->getAttribute('oauth_user_id'),
        ]);
    }

    private function isIssuedForThisResource(string $accessToken): bool
    {
        try {
            $audiences = (new Parser(new JoseEncoder()))->parse($accessToken)->claims()->get('aud', []);
        } catch (\Throwable) {
            return false;
        }

        return in_array(AccessTokenEntity::resource(), (array) $audiences, true);
    }
}
