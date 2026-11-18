<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth\Repository;

use ImMcpProvider\Module;
use ImMcpProvider\OAuth\Entity\AuthCodeEntity;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use RuntimeException;

final class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $handler = Module::identifiedHandler('authcode');
        $object = $handler->create();

        $object->setVar('identifier', $authCodeEntity->getIdentifier());
        $object->setVar('expires', $authCodeEntity->getExpiryDateTime()->getTimestamp());
        $object->setVar('created', time());

        if (! $handler->insert($object, true)) {
            throw new RuntimeException('Could not store the authorization code.');
        }
    }

    public function revokeAuthCode(string $codeId): void
    {
        Module::identifiedHandler('authcode')->revokeByIdentifier($codeId);
    }

    public function isAuthCodeRevoked(string $codeId): bool
    {
        return Module::identifiedHandler('authcode')->isRevoked($codeId);
    }
}
