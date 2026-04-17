<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Dto;

final readonly class OAuthServerMetadata
{
    public function __construct(
        public string $issuer,
        public string $authorizationEndpoint,
        public string $tokenEndpoint,
        public string $registrationEndpoint,
    ) {
    }
}
