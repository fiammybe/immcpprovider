<?php

declare(strict_types=1);

namespace ImMcpProvider\OAuth\Entity;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\ClientTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

final class ClientEntity implements ClientEntityInterface
{
    use ClientTrait;
    use EntityTrait;

    /** @param array<int, string> $redirectUris */
    public function __construct(string $identifier, string $name, array $redirectUris, bool $isConfidential)
    {
        $this->identifier = $identifier;
        $this->name = $name;
        $this->redirectUri = $redirectUris;
        $this->isConfidential = $isConfidential;
    }
}
