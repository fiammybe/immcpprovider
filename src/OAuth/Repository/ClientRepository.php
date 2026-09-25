<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth\Repository;

use ImMcpProvider\Module;
use ImMcpProvider\OAuth\Entity\ClientEntity;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;

final class ClientRepository implements ClientRepositoryInterface
{
    private const ALLOWED_GRANT_TYPES = ['authorization_code', 'refresh_token'];

    public function getClientEntity(string $clientIdentifier): ?ClientEntityInterface
    {
        $client = Module::identifiedHandler('client')->getByIdentifier($clientIdentifier);

        if ($client === null) {
            return null;
        }

        return new ClientEntity(
            $clientIdentifier,
            (string) $client->getVar('name', 'n'),
            $client->getRedirectUris(),
            (string) $client->getVar('secret_hash', 'n') !== ''
        );
    }

    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        if ($grantType !== null && ! in_array($grantType, self::ALLOWED_GRANT_TYPES, true)) {
            return false;
        }

        $client = Module::identifiedHandler('client')->getByIdentifier($clientIdentifier);

        if ($client === null) {
            return false;
        }

        $secretHash = (string) $client->getVar('secret_hash', 'n');

        if ($secretHash === '') {
            return true;
        }

        if ($clientSecret === null) {
            return false;
        }

        return password_verify($clientSecret, $secretHash);
    }
}
