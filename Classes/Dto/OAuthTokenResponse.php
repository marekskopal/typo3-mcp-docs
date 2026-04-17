<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Dto;

final readonly class OAuthTokenResponse
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
        public string $refreshToken,
    ) {
    }
}
