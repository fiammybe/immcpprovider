<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth;

use RuntimeException;

final class JsonWebKeySet
{
    public function __construct(private readonly KeyStore $keys)
    {
    }

    public static function create(): self
    {
        return new self(KeyStore::fromTrustPath());
    }

    /**
     * @return array{
     *     keys: array<int, array<string, string>>
     * }
     */
    public function toArray(): array
    {
        $publicKey = $this->keys->publicKey();
        $resource = openssl_pkey_get_public($publicKey);
        $details = $resource === false ? false : openssl_pkey_get_details($resource);

        if ($details === false || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('The OAuth public key is not a usable RSA key.');
        }

        return [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'use' => 'sig',
                    'alg' => 'RS256',
                    'kid' => substr(hash('sha256', $publicKey), 0, 16),
                    'n' => $this->base64Url($details['rsa']['n']),
                    'e' => $this->base64Url($details['rsa']['e']),
                ],
            ],
        ];
    }

    private function base64Url(string $binary): string
    {
        return rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');
    }
}
