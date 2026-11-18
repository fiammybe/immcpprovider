<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth\Repository;

use ImMcpProvider\Module;
use ImMcpProvider\OAuth\Entity\RefreshTokenEntity;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use RuntimeException;

final class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $handler = Module::identifiedHandler('refreshtoken');
        $object = $handler->create();

        $object->setVar('identifier', $refreshTokenEntity->getIdentifier());
        $object->setVar('accesstoken_identifier', $refreshTokenEntity->getAccessToken()->getIdentifier());
        $object->setVar('expires', $refreshTokenEntity->getExpiryDateTime()->getTimestamp());
        $object->setVar('created', time());

        if (! $handler->insert($object, true)) {
            throw new RuntimeException('Could not store the refresh token.');
        }
    }

    public function revokeRefreshToken(string $tokenId): void
    {
        Module::identifiedHandler('refreshtoken')->revokeByIdentifier($tokenId);
    }

    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        return Module::identifiedHandler('refreshtoken')->isRevoked($tokenId);
    }
}
