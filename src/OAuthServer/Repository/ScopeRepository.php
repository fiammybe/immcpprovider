<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth\Repository;

use ImMcpProvider\OAuth\Entity\ScopeEntity;
use ImMcpProvider\OAuth\Scope;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;

final class ScopeRepository implements ScopeRepositoryInterface
{
    public function getScopeEntityByIdentifier(string $identifier): ?ScopeEntityInterface
    {
        if ($identifier !== Scope::MCP) {
            return null;
        }

        return new ScopeEntity($identifier);
    }

    /** @param array<int, ScopeEntityInterface> $scopes */
    public function finalizeScopes(
        array $scopes,
        string $grantType,
        ClientEntityInterface $clientEntity,
        string|null $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        if ($scopes !== []) {
            return $scopes;
        }

        return [new ScopeEntity(Scope::MCP)];
    }
}
