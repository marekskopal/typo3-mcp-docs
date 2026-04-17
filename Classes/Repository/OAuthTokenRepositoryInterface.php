<?php

declare(strict_types=1);

namespace MarekSkopal\MsMcpDocs\Repository;

interface OAuthTokenRepositoryInterface
{
    /** @return array{content_element_uid: int, server_url: string, client_id: string, access_token: string, refresh_token: string, expires_at: int}|null */
    public function findByContentElementUid(int $contentElementUid): ?array;

    public function save(
        int $contentElementUid,
        string $serverUrl,
        string $clientId,
        string $accessToken,
        string $refreshToken,
        int $expiresAt,
    ): void;

    public function deleteByContentElementUid(int $contentElementUid): void;
}
