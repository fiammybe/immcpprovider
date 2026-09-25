<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth\Entity;

use DateTimeImmutable;
use ImMcpProvider\Module;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\CryptKeyInterface;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;
use RuntimeException;
use SensitiveParameter;

final class AccessTokenEntity implements AccessTokenEntityInterface
{
    use EntityTrait;
    use TokenEntityTrait;

    public const RESOURCE_PATH = 'api/mcp.php';

    private CryptKeyInterface $privateKey;

    public static function resource(): string
    {
        return Module::url(self::RESOURCE_PATH);
    }

    public function setPrivateKey(#[SensitiveParameter] CryptKeyInterface $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    public function toString(): string
    {
        $keyContents = $this->privateKey->getKeyContents();

        if ($keyContents === '') {
            throw new RuntimeException('Private key is empty');
        }

        $configuration = Configuration::forAsymmetricSigner(
            new Sha256(),
            InMemory::plainText($keyContents, $this->privateKey->getPassPhrase() ?? ''),
            InMemory::plainText('empty', 'empty')
        );

        $clientIdentifier = $this->getClient()->getIdentifier();
        $issuedAt = $this->wholeSeconds(new DateTimeImmutable());
        $expiresAt = $this->wholeSeconds($this->getExpiryDateTime());

        return $configuration->builder()
            ->issuedBy(Module::issuer())
            ->permittedFor(self::resource(), $clientIdentifier)
            ->identifiedBy($this->getIdentifier())
            ->issuedAt($issuedAt)
            ->canOnlyBeUsedAfter($issuedAt)
            ->expiresAt($expiresAt)
            ->relatedTo($this->getUserIdentifier() ?? $clientIdentifier)
            ->withClaim('client_id', $clientIdentifier)
            ->withClaim('scopes', $this->getScopes())
            ->getToken($configuration->signer(), $configuration->signingKey())
            ->toString();
    }

    private function wholeSeconds(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimestamp($moment->getTimestamp());
    }
}
