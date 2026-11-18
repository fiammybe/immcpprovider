<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth\Repository;

use ImMcpProvider\Module;
use ImMcpProvider\OAuth\Entity\AccessTokenEntity;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use RuntimeException;

final class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    private const PURGE_ONE_IN = 50;

    /** @param array<int, ScopeEntityInterface> $scopes */
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        string|null $userIdentifier = null
    ): AccessTokenEntityInterface {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);

        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }

        if ($userIdentifier !== null) {
            $accessToken->setUserIdentifier($userIdentifier);
        }

        return $accessToken;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $handler = Module::identifiedHandler('accesstoken');
        $object = $handler->create();

        $object->setVar('identifier', $accessTokenEntity->getIdentifier());
        $object->setVar('client_identifier', $accessTokenEntity->getClient()->getIdentifier());
        $object->setVar('user_id', (int) $accessTokenEntity->getUserIdentifier());
        $object->setVar('scopes', implode(' ', array_map(
            static fn (ScopeEntityInterface $scope): string => $scope->getIdentifier(),
            $accessTokenEntity->getScopes()
        )));
        $object->setVar('expires', $accessTokenEntity->getExpiryDateTime()->getTimestamp());
        $object->setVar('created', time());

        if (! $handler->insert($object, true)) {
            throw new RuntimeException('Could not store the access token.');
        }

        if (random_int(1, self::PURGE_ONE_IN) === 1) {
            Module::purgeExpired();
        }
    }

    public function revokeAccessToken(string $tokenId): void
    {
        Module::identifiedHandler('accesstoken')->revokeByIdentifier($tokenId);
    }

    public function isAccessTokenRevoked(string $tokenId): bool
    {
        return Module::identifiedHandler('accesstoken')->isRevoked($tokenId);
    }
}
