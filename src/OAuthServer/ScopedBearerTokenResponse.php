<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth;

use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use SensitiveParameter;

final class ScopedBearerTokenResponse extends BearerTokenResponse
{
    /** @return array<string, string> */
    protected function getExtraParams(#[SensitiveParameter] AccessTokenEntityInterface $accessToken): array
    {
        $scopes = array_map(
            static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(),
            $accessToken->getScopes()
        );

        return ['scope' => implode(' ', $scopes)];
    }
}
